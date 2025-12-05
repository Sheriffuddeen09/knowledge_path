<?php
// app/Http/Controllers/Auth/RegisterController.php
namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\OtpVerification;
use Carbon\Carbon;
use App\Mail\OtpMail;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Auth;


class RegisterController extends Controller
{
    // Send OTP
    public function sendOtp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email'
        ]);

        if($validator->fails()){
            return response()->json(["errors"=> $validator->errors()], 422);
        }

        $email = $request->email;
        $otp = rand(100000, 999999);
        $expired = Carbon::now()->addMinutes(10);

        // Remove old OTPs
        OtpVerification::where('email', $email)->delete();

        // Save new OTP
        OtpVerification::create([
            'email' => $email,
            'otp' => Hash::make($otp),
            'expired_at' => $expired,
            'verified' => false,
        ]);

        Mail::to($email)->send(new OtpMail($otp));

        return response()->json([
            'message' => 'OTP sent successfully.',
            'expires_at' => $expired
        ]);
    }

    // Verify OTP
    public function verifyOtp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'otp'   => 'required'
        ]);

        if($validator->fails()){
            return response()->json(["errors"=> $validator->errors()], 422);
        }

        $record = OtpVerification::where('email', $request->email)
            ->orderBy('created_at','desc')
            ->first();

        if(!$record){
            return response()->json(['message'=>'No OTP requested for this email.'], 404);
        }

        if($record->verified){
            return response()->json(['message'=>'OTP already verified.'], 400);
        }

        if(Carbon::now()->greaterThan($record->expired_at)){
            return response()->json(['message'=>'OTP expired.'], 400);
        }

        if(!Hash::check($request->otp, $record->otp)){
            return response()->json(['message'=>'Invalid OTP.'], 400);
        }

        $record->verified = true;
        $record->save();

        return response()->json(['message'=>'OTP verified successfully.']);
    }

    // Register
    public function register(Request $request)
{
    $validator = Validator::make($request->all(), [
        'first_name' => 'required|string|max:255',
        'last_name' => 'required|string|max:255',
        'dob' => 'required|date',
        'phone' => 'required|string|unique:users,phone',
        'email' => 'required|email|unique:users,email',
        'phone' => 'required|string|unique:users,phone',
        'location' => 'required|string',
        'location_country_code' => 'required|string',
        'gender' => 'required|in:male,female,other',
        'role' => 'required|in:student,admin',
        'password' => 'required|string|min:8|confirmed'
    ]);

    if ($validator->fails()) {
        return response()->json(["errors" => $validator->errors()], 422);
    }

    // Check OTP verification
    $otpRecord = OtpVerification::where('email', $request->email)
        ->orderBy('created_at', 'desc')
        ->first();

    if (!$otpRecord || !$otpRecord->verified) {
        return response()->json(['message' => 'Email not verified'], 400);
    }

    $user = User::create([
        'first_name' => $request->first_name,
        'last_name' => $request->last_name,
        'dob' => $request->dob,
        'phone' => $request->phone,
        'phone_country_code' => $request->phone_country_code,
        'location' => $request->location,
        'location_country_code' => $request->location_country_code,
        'email' => $request->email,
        'gender' => $request->gender,
        'role' => $request->role,
        'password' => Hash::make($request->password),
        'email_verified_at' => now()
    ]);

    $otpRecord->delete();

    // Automatically log the user in
    Auth::login($user);

    // Redirect based on role
    $redirect = $user->role === 'admin'
        ? '/admin/dashboard'
        : '/student/dashboard';

    return response()->json([
        'status' => true,
        'message' => 'Registration complete and logged in',
        'redirect' => $redirect,
        'user' => $user
    ], 201);
}


    public function checkBeforeNext(Request $request)
{
    try {
        $request->validate([
            'firstName' => 'required|string|max:255',
            'lastName' => 'required|string|max:255',
            'dob' => 'required|date',
            'gender' => 'required|in:male,female,other',
        ]);

        // Example DB check to trigger QueryException if server is down
        // You can remove if not needed
        $count = \App\Models\User::count();

        return response()->json(['message' => 'All good'], 200);

    } catch (\Illuminate\Database\QueryException $e) {
        // Database connection issues
        if ($e->getCode() === '2002') {
            return response()->json(['message' => 'Server down, please try later'], 500);
        }
        return response()->json(['message' => 'Server down, please try later'], 500);
    } catch (\Exception $e) {
        return response()->json(['message' => 'Something went wrong'], 500);
    }
}

}
