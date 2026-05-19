<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use App\Models\Chat;


class EncryptionController extends Controller
{
    public function savePublicKey(Request $request)
    {
        $request->validate([
            'public_key' => 'required|string'
        ]);

        auth()->user()->update([
            'public_key' => $request->public_key
        ]);

        return response()->json([
            'success' => true
        ]);
    }



    public function generateChatKey(Chat $chat)
    {
        $chatKey = base64_encode(random_bytes(32));

        $chat->update([
            'chat_key_user1' => encrypt($chatKey),
            'chat_key_user2' => encrypt($chatKey),
        ]);

        return response()->json([
            'chat_key_generated' => true
        ]);
    }


    }
