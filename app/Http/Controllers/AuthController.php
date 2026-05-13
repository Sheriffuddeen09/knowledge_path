<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\UserSession;
class AuthController extends Controller
{
    

public function switchAccount($id)
{
    $session = UserSession::with('user')
        ->findOrFail($id);

    return response()->json([

        'token' => $session->token,

        'user' => $session->user,

        'expires_at' => $session->expires_at
    ]);
}


public function accounts()
{
    return response()->json(

        UserSession::with('user')
            ->latest()
            ->get()
    );
}


public function removeAccount($id)
{
    $session = UserSession::findOrFail($id);

    // delete sanctum token
    $token = explode(
        '|',
        $session->token
    )[0] ?? null;

    if ($token) {

        \Laravel\Sanctum\PersonalAccessToken
            ::where('id', $token)
            ->delete();
    }

    $session->delete();

    return response()->json([
        'message' => 'Account removed'
    ]);
}

}
