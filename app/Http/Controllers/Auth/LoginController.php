<?php
namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;


class LoginController extends Controller
{
    // -----------------------------
    // SEND OTP
    // -----------------------------
    public function loginSendOtp(Request $request)
    {
        $request->validate(['email' => 'required|email']);

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json(['message' => 'Email not found'], 404);
        }

        $otp = rand(100000, 999999);

        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $request->email],
            [
                'otp' => $otp,
                'token' => null,       // avoid null error
                'created_at' => now()
            ]
        );

        Mail::raw("Your OTP code is $otp", function ($message) use ($request) {
            $message->to($request->email)->subject('Your Login OTP Code');
        });

        return response()->json(['message' => 'OTP sent']);
    }


      // -----------------------------
    // CHECK LOGIN USER IN
    // -----------------------------

    public function loginCheck(Request $request)
{
    $credentials = $request->only('email', 'password');

    if (!Auth::attempt($credentials)) {
        return response()->json(['message' => 'Invalid credentials'], 401);
    }

    $user = Auth::user();

    return response()->json([
        'message' => 'Login successful',
        'user' => $user,
        'token' => $user->createToken('api-token')->plainTextToken
    ]);
}

    // -----------------------------
    // VERIFY OTP AND LOG USER IN
    // -----------------------------
    public function loginVerifyOtp(Request $request)
{
    $request->validate([
        'email' => 'required|email',
        'otp' => 'required|numeric'
    ]);

    $record = DB::table('password_reset_tokens')
        ->where('email', $request->email)
        ->where('otp', $request->otp)
        ->first();

    if (!$record) {
        return response()->json(['message' => 'Invalid OTP Code'], 422);
    }

    $user = User::where('email', $request->email)->first();

    Auth::login($user); // <---- VERY IMPORTANT

    return response()->json([
        'message' => 'OTP Verified, Login Successful',
        'user' => $user
    ]);
}

   
    public function login(Request $request)
{
    $request->validate([
        'email' => 'required|email',
        'password' => 'required|string',
        'remember_me' => 'nullable|boolean'
    ]);

    // Get user
    $user = User::where('email', $request->email)->first();

    if (!$user || !Hash::check($request->password, $user->password)) {
        throw ValidationException::withMessages([
            'email' => ['Invalid email or password']
        ]);
    }

    // Delete old tokens
    $user->tokens()->delete();

    // Remember me
    $remember = $request->remember_me ? true : false;

    // Token expiration: 3 days if not remembering, 30 days if remembering
    $expiresAt = $remember
        ? now()->addDays(30)    // Remember Me enabled
        : now()->addDays(3);    // Auto logout after 3 days

    // Create Sanctum token
    $token = $user->createToken('auth_token', ['*'], $expiresAt)->plainTextToken;

    // Determine redirect URL based on role
    $role = strtolower(trim($user->role));

    $redirect = $role === 'student'
        ? '/student/dashboard'
        : '/admin/dashboard';


    return response()->json([
        'status' => true,
        'message' => $remember 
            ? 'Login successful — Remember Me enabled'
            : 'Login successful',
        'token' => $token,
        'expires_at' => $expiresAt,
        'user' => $user,
        'redirect' => $redirect
    ]);
}

    public function logout(Request $request)
    {
        $request->user()->tokens()->delete();
        return response()->json(['message' => 'Logged out']);
    }
}



