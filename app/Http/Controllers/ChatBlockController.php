<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Chat;
use App\Models\Notification;
use App\Events\ChatBlocked;
use App\Events\ChatUnblocked;



class ChatBlockController extends Controller
{
    public function block(Request $request, Chat $chat)
{
    $userId = auth()->id();

    // ✅ Determine other user correctly
    if ($chat->type === 'student_teacher') {
        $otherId = $chat->teacher_id == $userId
            ? $chat->student_id
            : $chat->teacher_id;
    } else {
        $otherId = $chat->user_one_id == $userId
            ? $chat->user_two_id
            : $chat->user_one_id;
    }

    if (!$otherId) {
        return response()->json(['message' => 'Invalid chat users'], 400);
    }

    $chat->blocks()->updateOrCreate(
        ['chat_id' => $chat->id],
        [
            'blocker_id' => $userId,
            'blocked_id' => $otherId
        ]
    );

    // 🔔 Create notification

    Notification::create([
        'user_id' => $otherId,
        'type' => 'chat_blocked',
        'data' => json_encode([
            'chat_id' => $chat->id,
            'other_user_id' => $userId,
            'first_name' => auth()->user()->first_name,
            'last_name' => auth()->user()->last_name,
            'full_name' => auth()->user()->first_name . ' ' . auth()->user()->last_name,
        ]),
        'read' => false
    ]);

    broadcast(new ChatBlocked($chat))->toOthers();

    return response()->json([
        'message' => 'User blocked',
        'block_info' => [
            'blocked' => true,
            'blocker_id' => $userId,
            'blocked_id' => $otherId
        ]
    ]);
}

public function unblock(Request $request, Chat $chat)
{
    $userId = auth()->id();
    $block = $chat->blocks()->first();

    if (!$block) {
        return response()->json(['message' => 'No block found'], 404);
    }

    if ($block->blocker_id !== $userId) {
        return response()->json(['message' => 'You cannot unblock this user'], 403);
    }

    $otherId = $block->blocked_id;

    $block->delete();

    // 🔔 Notification
    // 🔔 Notification
        Notification::create([
            'user_id' => $otherId,
            'type' => 'chat_unblocked',
            'data' => json_encode([
                'chat_id' => $chat->id,
                'other_user_id' => $userId,
                'first_name' => auth()->user()->first_name,
                'last_name' => auth()->user()->last_name,
                'full_name' => auth()->user()->first_name . ' ' . auth()->user()->last_name,
            ]),
            'read' => false
        ]);

    broadcast(new ChatUnblocked($chat))->toOthers();

    return response()->json([
        'message' => 'User unblocked',
        'block_info' => null
    ]);
}

}
