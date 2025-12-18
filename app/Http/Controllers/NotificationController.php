<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\LiveClassRequest;
use App\Models\Message;

class NotificationController extends Controller
{
    public function requestCount(Request $request)
    {
        $user = $request->user();

        if ($user->role === 'teacher') {
            $count = LiveClassRequest::where('teacher_id', $user->id)
                ->where('status', 'pending')
                ->count();
        } else {
            $count = LiveClassRequest::where('user_id', $user->id)
                ->where('status', 'pending')
                ->count();
        }

        return response()->json([
            'pending_requests' => $count
        ]);
    }

    // 🔴 Unread chat messages badge
    public function messageCount(Request $request)
{
    $userId = $request->user()->id;

    $count = Message::whereNull('seen_at')
        ->where('sender_id', '!=', $userId)
        ->whereHas('chat.users', function ($q) use ($userId) {
            $q->where('users.id', $userId);
        })
        ->count();

    return response()->json(['unread_messages' => $count]);
}

public function markAsRead($chatId, Request $request)
{
    Message::where('chat_id', $chatId)
        ->whereNull('seen_at')
        ->where('sender_id', '!=', $request->user()->id)
        ->update(['seen_at' => now()]);

    return response()->json(['status' => true]);
}

}
