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
        'post_id' => $post->id,
        'user_id' => auth()->id(),
        'parent_id' => $request->parent_id,
        'body' => $body,
    ]);

    if ($request->hasFile('image')) {
        $comment->image = $request->file('image')->store('comments', 'public');
        $comment->save();
    }

    // -------------------------
    // 2️⃣ Notify post/comment owner
    $recipientId = null;
    $notificationType = 'post_comment';
    $reactorsOrCommenters = [];

    if ($comment->parent_id) {
    // Reply to a comment -> notify parent comment owner
    $parentComment = PostComment::find($comment->parent_id);
    if ($parentComment && $parentComment->user_id !== auth()->id()) {
        $recipientId = $parentComment->user_id;
        $commenters = PostComment::where('parent_id', $parentComment->id)
            ->with('user')
            ->get();

        // 👈 Map full names
        $reactorsOrCommenters = $commenters->map(function ($c) {
            $u = $c->user;
            return trim(($u->first_name ?? '') . ' ' . ($u->last_name ?? ''));
        })->toArray();
    }
} else {
    // Top-level comment -> notify post owner
    if ($post->user_id !== auth()->id()) {
        $recipientId = $post->user_id;
        $commenters = PostComment::where('post_id', $post->id)
            ->whereNull('parent_id')
            ->with('user')
            ->get();

        // 👈 Map full names
        $reactorsOrCommenters = $commenters->map(function ($c) {
            $u = $c->user;
            return trim(($u->first_name ?? '') . ' ' . ($u->last_name ?? ''));
        })->toArray();
    }
}
    if ($recipientId) {
        Notification::updateOrCreate(
            [
                'user_id' => $recipientId,
                'type' => $notificationType,
                'data' => json_encode(['comment_id' => $comment->id]),
            ],
            [
                'data' => json_encode([
                    'post_id' => $post->id,
                    'commenters' => $reactorsOrCommenters,
                    'count' => count($reactorsOrCommenters),
                ]),
                'redirect_url' => "/post/{$post->id}#comments",
                'read' => false,
            ]
        );
    }

    // -------------------------
    // 3️⃣ Handle mentions in body
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
                    'type' => 'mention',
                    'data' => json_encode([
                        'comment_id' => $comment->id,
                        'post_id' => $post->id,
                        'mentioned_by' => auth()->user()->first_name.' '.auth()->user()->last_name,
                    ]),
                    'redirect_url' => "/post/{$post->id}#comment-{$comment->id}",
                    'read' => false,
                ]);
            }
        }
    }

    // -------------------------
    // 4️⃣ Load relations for frontend
    $comment->load([
        'user',
        'replies.user',
        'reactions.user'
    ]);

    return response()->json([
        'comment' => $comment
    ]);
}


public function reactions($commentId)
{
    $comment = PostComment::with('reactions.user')->findOrFail($commentId);

    // Group reactions by emoji and map user names
    $grouped = $comment->reactions
        ->groupBy('emoji')
        ->map(function ($items) {
            return $items->map(fn($r) => [
                'id' => $r->user->id,
                'name' => trim(($r->user->first_name ?? '') . ' ' . ($r->user->last_name ?? '')),
            ]);
        });

    // Build human-readable messages per emoji
    $messages = $grouped->map(function ($users, $emoji) {
        $count = $users->count();
        if ($count === 1) {
            return "$emoji reacted by " . $users[0]['name'];
        } elseif ($count === 2) {
            return "$emoji reacted by " . $users[0]['name'] . " and " . $users[1]['name'];
        } elseif ($count > 2) {
            return "$emoji reacted by " . $users[0]['name'] . " and " . ($count - 1) . " others";
        }
    });

    // Get the authenticated user's reaction if any
    $myReaction = auth()->check()
        ? $comment->reactions()->where('user_id', auth()->id())->value('emoji')
        : null;

    // -----------------------------
    // -----------------------------
// Create/update notification for comment/reply owner
$currentUser = auth()->user();
if ($comment->user_id !== $currentUser->id) {

    $reactors = $comment->reactions->map(function($r) {
        $u = $r->user;
        return trim(($u->first_name ?? '') . ' ' . ($u->last_name ?? ''));
    })->toArray();

    Notification::updateOrCreate(
        [
            'user_id' => $comment->user_id,
            'type' => 'comment_reaction',
            'data' => json_encode(['comment_id' => $comment->id]),
        ],
        [
            'data' => json_encode([
                'comment_id' => $comment->id,
                'reactors' => $reactors, // full names now
                'count' => count($reactors),
            ]),
            'redirect_url' => "/post/{$comment->post_id}#comment-{$comment->id}",
            'read' => false,
        ]
    );
}

    return response()->json([
        'reactions' => $grouped,
        'messages' => $messages,
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
