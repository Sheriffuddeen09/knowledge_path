<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\BlockedUser;

class BlockController extends Controller
{
    public function block(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id'
        ]);

        BlockedUser::firstOrCreate([
            'blocker_id' => auth()->id(),
            'blocked_id' => $request->user_id,
        ]);

        return response()->json(['message' => 'User blocked']);
    }

    public function unblock(Request $request)
    {
        BlockedUser::where([
            'blocker_id' => auth()->id(),
            'blocked_id' => $request->user_id,
        ])->delete();

        return response()->json(['message' => 'User unblocked']);
    }
}

