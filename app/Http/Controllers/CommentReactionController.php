<?php

namespace App\Http\Controllers;

use App\Models\Comment;
use Illuminate\Http\Request;

class CommentReactionController extends Controller
{
    public function toggle(Request $request, Comment $comment)
    {
        $request->validate([
            'emoji' => 'required|string'
        ]);

        if (!auth()->check()) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $comment->reactions()->updateOrCreate(
            ['user_id' => auth()->id()],
            ['emoji' => $request->emoji]
        );

        $reactions = $comment->reactions()
            ->selectRaw('emoji, COUNT(*) as count')
            ->groupBy('emoji')
            ->pluck('count', 'emoji');

        return response()->json([
    'reactions' => $reactions,
    'user_reaction' => $request->emoji
        ]);

    }
}
