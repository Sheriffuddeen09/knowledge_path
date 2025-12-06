<?php 

namespace App\Http\Controllers;

use App\Models\Reply;
use App\Models\Comment;
use Illuminate\Http\Request;

class ReplyController extends Controller
{
    // Add a reply
    public function store(Request $request)
    {
        $data = $request->validate([
            'comment_id' => 'required|exists:comments,id',
            'reply' => 'required|string',
            'emoji' => 'nullable|string'
        ]);

        $reply = Reply::create([
            'comment_id' => $data['comment_id'],
            'reply' => $data['reply'],
            'emoji' => $data['emoji'],
            'user_id' => auth()->id(),
            'likes' => []
        ]);

        return response()->json(['status' => true, 'reply' => $reply]);
    }

    // Like a reply with emoji
    public function likeReply(Request $request, $id)
    {
        $reply = Reply::findOrFail($id);

        $emoji = $request->emoji;

        $likes = $reply->likes ?? [];

        if (isset($likes[auth()->id()]) && $likes[auth()->id()] == $emoji) {
            unset($likes[auth()->id()]);
        } else {
            $likes[auth()->id()] = $emoji;
        }

        $reply->likes = $likes;
        $reply->save();

        return response()->json(['status' => true, 'likes' => $likes]);
    }

    // Delete reply (Admin only)
    public function destroy($id)
    {
        $reply = Reply::findOrFail($id);

        if (auth()->user()->role !== 'admin') {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $reply->delete();

        return response()->json(['status' => true]);
    }
}
