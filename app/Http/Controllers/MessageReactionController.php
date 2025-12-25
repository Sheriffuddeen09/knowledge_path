<?php

namespace App\Http\Controllers;

use App\Models\Message;
use App\Models\MessageReaction;
use Illuminate\Http\Request;

class MessageReactionController extends Controller
{
    public function react(Request $request)
    {
        $request->validate([
            'message_id' => 'required|exists:messages,id',
            'emoji' => 'required|string|max:10',
        ]);

        $reaction = MessageReaction::updateOrCreate(
            [
                'message_id' => $request->message_id,
                'user_id' => auth()->id(),
            ],
            [
                'emoji' => $request->emoji,
            ]
        );

        return response()->json($reaction->load('user:id,first_name'));
    }
}
