<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Post;
use App\Models\PostComment;
use App\Models\PostCommentReaction;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
 use App\Models\User;
use App\Models\Notification;

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
    ->whereNull('parent_id') // only top-level comments
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

    if ((!$request->filled('body') || trim($request->body) === '') &&
        !$request->hasFile('image')) {
        return response()->json([
            'message' => 'Comment text or image is required'
        ], 422);
    }

    $body = $request->body ?? '';

    // -------------------------
    // 1️⃣ Create comment/reply
    $comment = PostComment::create([
        'post_id'   => $post->id,
        'user_id'   => auth()->id(),
        'parent_id' => $request->parent_id,
        'body'      => $body,
    ]);

    if ($request->hasFile('image')) {
        $comment->image = $request->file('image')->store('comments', 'public');
        $comment->save();
    }

    // -------------------------
    // 2️⃣ Detect mentions FIRST
    $mentionedUserIds = [];
    preg_match_all('/@([\w]+)/', $body, $matches);

    if (!empty($matches[1])) {
        foreach ($matches[1] as $mentionedName) {

            $mentionedUser = User::where('first_name', $mentionedName)
                ->orWhere('last_name', $mentionedName)
                ->first();

            if ($mentionedUser && $mentionedUser->id !== auth()->id()) {
                $mentionedUserIds[] = $mentionedUser->id;

                Notification::create([
                    'user_id' => $mentionedUser->id,
                    'type'    => 'mention',
                    'data'    => json_encode([
                        'comment_id'   => $comment->id,
                        'post_id'      => $post->id,
                        'mentioned_by' => auth()->user()->first_name . ' ' . auth()->user()->last_name,
                    ]),
                    'redirect_url' => "/post/{$post->id}#comment-{$comment->id}",
                    'read' => false,
                ]);
            }
        }
    }

    // Remove duplicates
    $mentionedUserIds = array_unique($mentionedUserIds);

    // -------------------------
    // 3️⃣ Determine recipient for comment/reply notification
    $recipientId = null;
    $commenters  = collect();

    if ($comment->parent_id) {

        // 🔥 REPLY
        $parentComment = PostComment::find($comment->parent_id);

        if ($parentComment &&
            $parentComment->user_id !== auth()->id() &&
            !in_array($parentComment->user_id, $mentionedUserIds)) {

            $recipientId = $parentComment->user_id;

            $commenters = PostComment::where('parent_id', $parentComment->id)
                ->with('user')
                ->get()
                ->unique('user_id');
        }

    } else {

        // 🔥 TOP LEVEL COMMENT
        if ($post->user_id !== auth()->id() &&
            !in_array($post->user_id, $mentionedUserIds)) {

            $recipientId = $post->user_id;

            $commenters = PostComment::where('post_id', $post->id)
                ->whereNull('parent_id')
                ->with('user')
                ->get()
                ->unique('user_id');
        }
    }

    // -------------------------
    // 4️⃣ Create grouped comment/reply notification
    if ($recipientId) {

        $commenterNames = $commenters->map(function ($c) {
            $u = $c->user;
            return trim(($u->first_name ?? '') . ' ' . ($u->last_name ?? ''));
        })->values()->toArray();

        Notification::updateOrCreate(
            [
                'user_id'  => $recipientId,
                'type'     => 'post_comment',
                'post_id'  => $post->id,
                'parent_id'=> $comment->parent_id,
            ],
            [
                'data' => json_encode([
                    'post_id'    => $post->id,
                    'commenters' => $commenterNames,
                    'count'      => count($commenterNames),
                    'parent_id'  => $comment->parent_id,
                ]),
                'redirect_url' => "/post/{$post->id}#comments",
                'read' => false,
            ]
        );
    }

    // -------------------------
    // 5️⃣ Load relations for frontend
    $comment->load([
        'user',
        'replies.user',
        'reactions.user'
    ]);

    return response()->json([
        'comment' => $comment
    ]);
}


public function reactions(PostComment $comment)
{
    $comment->load('reactions.user');

    $grouped = $comment->reactions
        ->groupBy('emoji')
        ->map(function ($items) {
            return $items->map(fn($r) => [
                'id' => $r->user->id,
                'name' => trim(($r->user->first_name ?? '') . ' ' . ($r->user->last_name ?? '')),
            ]);
        });

    $myReaction = auth()->check()
        ? $comment->reactions()
            ->where('user_id', auth()->id())
            ->value('emoji')
        : null;

    return response()->json([
        'reactions' => $grouped,
        'my_reaction' => $myReaction,
    ]);
}


public function toggleReaction(Request $request, PostComment $comment)
{
    $userId = auth()->id();
    $emoji = $request->emoji;

    // 1️⃣ Save or update the reaction
    $comment->reactions()->updateOrCreate(
        ['user_id' => $userId],
        ['emoji' => $emoji]
    );

    // Determine type FIRST
    $type = !empty($comment->parent_id)
        ? 'comment_reaction_reply'
        : 'comment_reaction_comment';

    // 2️⃣ Notify owner (skip self-reaction)
    if ($comment->user_id !== $userId) {

        $reactors = $comment->reactions()
            ->with('user')
            ->get()
            ->map(function ($r) {
                $u = $r->user;
                return trim(($u->first_name ?? '') . ' ' . ($u->last_name ?? ''));
            })
            ->toArray();

        Notification::updateOrCreate(
            [
                'user_id'   => $comment->user_id,
                'type'      => $type,               // ✅ use dynamic type
                'comment_id'=> $comment->id,        // ✅ real column
            ],
            [
                'data' => json_encode([
                    'comment_id' => $comment->id,
                    'reactors'   => $reactors,
                    'count'      => count($reactors),
                    'emoji'      => $emoji
                ]),
                'redirect_url' => "/post/{$comment->post_id}#comment-{$comment->id}",
                'read' => false,
            ]
        );
    }

    return response()->json([
        'reactions'   => $comment->reactions()->with('user')->get(),
        'my_reaction' => $emoji
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
