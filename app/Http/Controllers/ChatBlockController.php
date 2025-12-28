<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Chat;
use App\Events\ChatBlocked;
use App\Events\ChatUnblocked;



class ChatBlockController extends Controller
{
    public function block(Request $request, Chat $chat)
{
    $userId = auth()->id();
    $otherId = $chat->teacher_id === $userId ? $chat->student_id : $chat->teacher_id;

    // Only one block per chat
    $chat->blocks()->updateOrCreate(
        ['chat_id' => $chat->id],
        ['blocker_id' => $userId, 'blocked_id' => $otherId]
    );

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

    // ✅ Get the block record (NOT collection)
    $block = $chat->blocks()->first();

    if (!$block) {
        return response()->json(['message' => 'No block found'], 404);
    }

    // ✅ Only blocker can unblock
    if ($block->blocker_id !== $userId) {
        return response()->json(['message' => 'You cannot unblock this user'], 403);
    }

    // ✅ Delete block
    $block->delete();

    broadcast(new ChatUnblocked($chat))->toOthers();


    return response()->json([
        'message' => 'User unblocked',
        'block_info' => null
    ]);
}


}
