<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Post;
use App\Models\PostComment;
use App\Models\PostCommentReaction;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PostCommentController extends Controller
{

    public function index($postId)
{
    $comments = PostComment::with([
        'user:id,first_name,last_name,image',
        'replies.user:id,first_name,last_name,image',
        'replies.replies.user:id,first_name,last_name,image',
        'reactions.user',
    ])
    ->where('post_id', $postId)
    ->whereNull('parent_id') // only top-level comment_reactions
    ->latest()
    ->get();

    return response()->json([
        'comments' => $comments
    ]);
}



    public function store(Request $request, Post $post)
{
    $request->validate([
        'body' => 'nullable|string',
        'parent_id' => 'nullable|exists:post_comments,id',
        'image' => 'nullable|image|max:4096',
    ]);

    if (
        (!$request->filled('body') || trim($request->body) === '') &&
        !$request->hasFile('image')
    ) {
        return response()->json([
            'message' => 'Comment text or image is required'
        ], 422);
    }

    $body = $request->body ?? '';

    // 🔥 Mention user if replying

    $comment = PostComment::create([
        'post_id'  => $post->id,
        'user_id'  => auth()->id(),
        'parent_id'=> $request->parent_id,
        'body'     => $body,
    ]);

    if ($request->hasFile('image')) {
        $comment->image = $request->file('image')
            ->store('comments', 'public');
        $comment->save();
    }

    $comment->load([
        'user',
        'replies.user',
        'reactions.user'
    ]);

    return response()->json([
        'comment' => $comment
    ]);
}



 public function react(Request $request, $commentId)
{
    $request->validate(['emoji' => 'required|string|max:10']);

    $comment = PostComment::findOrFail($commentId);

    PostCommentReaction::updateOrCreate(
        [
            'comment_id' => $comment->id,
            'user_id' => auth()->id(),
        ],
        [
            'emoji' => $request->emoji,
        ]
    );

    // reload reactions
    $comment->load('reactions.user');

    $grouped = $comment->reactions
        ->groupBy('emoji')
        ->map(function ($items) {
            return $items->map(fn($r) => [
                'id' => $r->user->id,
                'name' => $r->user->name,
            ]);
        });

    $myReaction = $comment->reactions()
        ->where('user_id', auth()->id())
        ->value('emoji');

    return response()->json([
        'status' => true,
        'reactions' => $grouped,
        'my_reaction' => $myReaction,
    ]);
}



public function reactions($commentId)
{
    $comment = PostComment::with('reactions.user')->findOrFail($commentId);

    $grouped = $comment->reactions
        ->groupBy('emoji')
        ->map(function ($items) {
            return $items->map(fn($r) => [
                'id' => $r->user->id,
                'name' => $r->user->first_name.' '.$r->user->last_name,
            ]);
        });

    $myReaction = null;
    if (auth()->check()) {
        $myReaction = $comment->reactions()
            ->where('user_id', auth()->id())
            ->value('emoji');
    }

    return response()->json([
        'reactions' => $grouped,
        'my_reaction' => $myReaction,
    ]);
}



    public function update(Request $request, PostComment $comment)
    {

    $data = $request->validate([
        'body' => 'required|string|min:1'
    ]);

    $comment->update([
        'body' => trim($data['body'])
    ]);

    $comment->load([
        'user',
        'replies.user',
        'reactions.user'
    ]);

    return response()->json([
        'status' => true,
        'comment' => $comment
    ]);
}


    public function destroy(PostComment $comment)
{

    $comment->delete();

    return response()->json([
        'status' => true
    ]);
}

}
