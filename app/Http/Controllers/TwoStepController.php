<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\UserSession;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use App\Events\UserOnline;
use Illuminate\Http\Request;


class TwoStepController extends Controller
{

    public function verify(Request $request)
{
    $request->validate([
        'user_id' => 'required|exists:users,id',
        'pin' => 'required|digits:6',
        'remember_me' => 'nullable|boolean'
    ]);

    $user = User::findOrFail(
        $request->user_id
    );

    // CHECK PIN
    if (
        !$user->two_step_pin ||
        !Hash::check(
            $request->pin,
            $user->two_step_pin
        )
    ) {

        throw ValidationException::withMessages([
            'pin' => ['Invalid PIN']
        ]);
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

    // UPDATE VERIFIED DATE
    $user->two_step_verified_at =
        now();

    $user->save();

    event(new UserOnline(
        (int) $user->id
    ));

    return response()->json([

        'status' => true,

        'message' =>
            'Two-step verified successfully',

        'token' => $token,

        'expires_at' => $expiresAt,

        'user' => $user,

        'redirect' => $redirect
    ]);
}

    public function status(Request $request)
    {
        return response()->json([
            'enabled' =>
                (bool) $request->user()->two_step_enabled
        ]);
    }

    public function setup(Request $request)
    {
        $request->validate([
            'pin' => 'required|digits:6'
        ]);

        $user = $request->user();

        $user->two_step_enabled = true;

        $user->two_step_pin =
            Hash::make($request->pin);

        $user->save();

        return response()->json([
            'message' =>
                'Two-step enabled successfully'
        ]);
    }

    public function change(Request $request)
    {
        $request->validate([
            'pin' => 'required|digits:6'
        ]);

        $user = $request->user();

        $user->two_step_pin =
            Hash::make($request->pin);

        $user->save();

        return response()->json([
            'message' =>
                'PIN changed successfully'
        ]);
    }

    public function remove(Request $request)
    {
        $user = $request->user();

        $user->two_step_enabled = false;

        $user->two_step_pin = null;

        $user->save();

        return response()->json([
            'message' =>
                'Two-step turned off'
        ]);
    }
}