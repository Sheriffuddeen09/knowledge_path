<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Post;
use App\Models\PostComment;

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
    if ($request->parent_id) {
        $parent = PostComment::with('user')->find($request->parent_id);

        if ($parent) {
            $body = '@' .
                $parent->user->first_name . ' ' .
                $parent->user->last_name . ' ' .
                $body;
        }
    }

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



    public function react(Request $request, PostComment $comment)
{
    $request->validate([
        'emoji' => 'required|string'
    ]);

    $reaction = $comment->reactions()
        ->updateOrCreate(
            [
                'user_id' => auth()->id(),
            ],
            [
                'emoji' => $request->emoji,
            ]
        );

    return response()->json([
        'reaction' => $reaction
    ]);
}


    public function update(Request $request, PostComment $comment)
{
    $this->authorize('update', $comment);

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
    $this->authorize('delete', $comment);

    $comment->delete();

    return response()->json([
        'status' => true
    ]);
}

}
