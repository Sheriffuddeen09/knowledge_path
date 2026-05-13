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
use App\Events\UserOnline;
use App\Events\UserOffline;
use App\Models\UserSession;

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

    $user = User::where(
        'email',
        $request->email
    )->first();

    if (
        !$user ||
        !Hash::check(
            $request->password,
            $user->password
        )
    ) {

        throw ValidationException::withMessages([
            'email' => [
                'Invalid email or password'
            ]
        ]);
    }

    if ($user->two_step_enabled) {

        $requiresPin = false;

        // NEVER VERIFIED
        if (
            !$user->two_step_verified_at
        ) {

            $requiresPin = true;
        }

        // MORE THAN 30 DAYS
        elseif (
            now()->diffInDays(
                $user->two_step_verified_at
            ) > 30
        ) {

            $requiresPin = true;
        }

        if ($requiresPin) {

            return response()->json([

                'status' => false,

                'requires_two_step' => true,

                'message' =>
                    'Two-step verification required',

                'user_id' => $user->id,

            ]);
        }
    }

    $remember =
        $request->remember_me
        ? true
        : false;

    $expiresAt = $remember
        ? now()->addDays(30)
        : now()->addDays(3);

    $token = $user->createToken(
        'auth_token',
        ['*'],
        $expiresAt
    )->plainTextToken;

    UserSession::updateOrCreate(

        [
            'user_id' => $user->id
        ],

        [
            'token' => $token,
            'expires_at' => $expiresAt
        ]
    );

    $role = strtolower(
        trim($user->role)
    );

    $redirect =
        $role === 'student'
        ? '/student/dashboard'
        : '/admin/dashboard';

    // UPDATE LAST VERIFIED
    $user->two_step_verified_at =
        now();

    $user->save();

    event(new UserOnline(
        (int) $user->id
    ));

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
        event(new UserOffline(auth()->id()));
        $request->user()->tokens()->delete();
        return response()->json([
            'status' => true,
            'message' => 'Logged out'
        ]);
    }

   public function deleteAccount(Request $request)
{
    $user = auth()->user();

    if (!$user) {
        return response()->json([
            'status' => false,
            'message' => 'Unauthorized'
        ], 401);
    }

    if (method_exists($user, 'tokens')) {
        $user->tokens()->delete();
    }

    $user->delete();

    return response()->json([
        'status' => true,
        'message' => 'Account Deleted'
    ]);
}


}



