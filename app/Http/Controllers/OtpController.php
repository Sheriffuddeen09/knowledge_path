<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\OtpVerification;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Hash;
use App\Mail\OtpMail;
use Carbon\Carbon;
use Illuminate\Support\Facades\Validator;

class OtpController extends Controller
{
    // Send OTP
    public function sendOtp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
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

        // Send OTP email
        Mail::to($email)->send(new OtpMail($otp));

        return response()->json([
            'message' => 'OTP sent successfully.',
            'expires_at' => $expired,
        ]);
    }

    // Verify OTP
    public function verifyOtp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'otp' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $record = OtpVerification::where('email', $request->email)
            ->orderBy('created_at', 'desc')
            ->first();

        if (!$record) {
            return response()->json(['message' => 'No OTP requested for this email.'], 404);
        }

        if ($record->verified) {
            return response()->json(['message' => 'OTP already verified.'], 400);
        }

        if (Carbon::now()->greaterThan($record->expired_at)) {
            return response()->json(['message' => 'OTP expired.'], 400);
        }

        if (!Hash::check($request->otp, $record->otp)) {
            return response()->json(['message' => 'Invalid OTP.'], 400);
        }

        $record->verified = true;
        $record->save();

        return response()->json(['message' => 'OTP verified successfully.']);
    }
}
