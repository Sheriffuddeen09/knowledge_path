<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ReplyReaction;

class ReplyController extends Controller
{
    public function react(Request $request, $replyId)
    {
        $emoji = $request->input('emoji');

        // Optional: use a session ID or 'guest' identifier instead of user_id
        $userId = auth()->id() ?? null; // allow null for guests

        // Create reaction; user_id can be null for guests
        $reaction = ReplyReaction::updateOrCreate(
            [
                'reply_id' => $replyId,
                'user_id' => $userId,
                'emoji' => $emoji,
            ]
        );

        // Fetch all reactions for this reply
        $reactions = ReplyReaction::where('reply_id', $replyId)
            ->get()
            ->groupBy('emoji')
            ->map(fn($users, $emoji) => $users->pluck('user_id')->toArray());

        return response()->json([
            'success' => true,
            'reactions' => $reactions
        ]);
    }
}
