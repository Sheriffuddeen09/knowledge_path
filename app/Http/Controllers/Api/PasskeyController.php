<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Passkey;
use App\Models\User;
use Illuminate\Http\Request;

class PasskeyController extends Controller
{
    public function status(Request $request)
    {
        $user = $request->user();

        return response()->json([
            'enabled' => $user->passkeys()->exists(),
            'passkeys' => $user->passkeys
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'credential_id' => 'required',
            'public_key' => 'required',
            'name' => 'nullable'
        ]);

        $user = $request->user();

        Passkey::create([
            'user_id' => $user->id,
            'credential_id' => $request->credential_id,
            'public_key' => $request->public_key,
            'name' => $request->name
        ]);

        return response()->json([
            'message' => 'Passkey created successfully'
        ]);
    }

    public function destroy($id)
    {
        Passkey::findOrFail($id)->delete();

        return response()->json([
            'message' => 'Passkey removed'
        ]);
    }


    public function generateLoginOptions(Request $request)
{
    $email = $request->email;

    $user = User::where('email', $email)->first();

    if (!$user) {
        return response()->json(['message' => 'User not found'], 404);
    }

    return response()->json([
        'challenge' => base64_encode(random_bytes(32)),
        'allowCredentials' => $user->passkeys->map(function ($key) {
            return [
                'id' => $key->credential_id,
                'type' => 'public-key',
            ];
        }),
        'userId' => $user->id
    ]);
}

public function verifyLogin(Request $request)
{
    $credentialId = $request->id;

    $passkey = Passkey::where('credential_id', $credentialId)->first();

    if (!$passkey) {
        return response()->json([
            'message' => 'Invalid passkey'
        ], 401);
    }

    $user = $passkey->user;

    $token = $user->createToken('auth_token')->plainTextToken;

    $role = strtolower(trim($user->role));

    $redirect = $role === 'student'
        ? '/student/dashboard'
        : '/admin/dashboard';

    return response()->json([
        'token' => $token,
        'user' => $user,
        'redirect' => $redirect,
        'message' => 'Login successful'
    ]);
}


}