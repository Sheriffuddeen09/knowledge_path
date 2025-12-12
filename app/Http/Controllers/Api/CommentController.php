<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Comment;
use App\Models\Video;

class CommentController extends Controller
{
    // Fetch all comments for a video
   public function index($videoId)
{
    $comments = Comment::with([
        'user',                  // user for the main comment
        'replies.user',          // user for first-level replies
        'replies.replies.user',  // user for second-level replies (if you support nested)
        'reactions.user',        // include user for reactions if needed
    ])
    ->where('video_id', $videoId)
    ->whereNull('parent_id')   // only top-level comments
    ->latest()
    ->get();

    return response()->json([
        'comments' => $comments
    ]);
}

    public function store(Request $request, Video $video)
{
    // Validate the request
    $request->validate([
        'body' => 'nullable|string',
        'parent_id' => 'nullable|exists:comments,id',
        'image' => 'nullable|image|max:4096', // must be a real file
    ]);

    // Make sure at least body or image exists
    if (
        (!$request->filled('body') || trim($request->body) === '') &&
        !$request->hasFile('image')
    ) {
        return response()->json([
            'message' => 'Comment text or image is required'
        ], 422);
    }

    // Save comment
    $comment = Comment::create([
        'body' => $request->body ?? '',
        'video_id' => $video->id,
        'parent_id' => $request->parent_id,
        'user_id' => auth()->id(),
    ]);

    // Save image if uploaded
    if ($request->hasFile('image')) {
        $path = $request->file('image')->store('comments', 'public');
        $comment->image = $path;
        $comment->save();
    }

    $comment->load(['user', 'reactions.user', 'replies']);

    return response()->json([
        'comment' => $comment
    ]);
}

public function react(Request $request, Comment $comment)
{
    $request->validate(['emoji' => 'required|string']);

    $reactions = $comment->reactions ? json_decode($comment->reactions, true) : [];
    $emoji = $request->emoji;
    $userId = auth()->id();

    // Ensure array exists
    if (!isset($reactions[$emoji]) || !is_array($reactions[$emoji])) {
        $reactions[$emoji] = [];
    }

    // Toggle reaction (optional)
    if (!in_array($userId, $reactions[$emoji])) {
        $reactions[$emoji][] = $userId;
    }

    $comment->reactions = json_encode($reactions);
    $comment->save();

    // ⭐ RETURN EXACT STRUCTURE YOUR FRONTEND EXPECTS
    return response()->json([
        'reactions' => $reactions,
    ]);
}


public function update(Request $request, Comment $comment)
{
    $this->authorize('update', $comment);

    $data = $request->validate([
        'body' => 'required|string|min:1'
    ]);

    // ✅ Trim to avoid "   "
    $body = trim($data['body']);

    if ($body === '') {
        return response()->json([
            'status' => false,
            'message' => 'Nothing to update'
        ], 422);
    }

    $comment->update([
        'body' => $body
    ]);

    $comment->load([
        'user',
        'reactions.user',
        'replies.user',
        'replies.reactions.user'
    ]);

    return response()->json([
        'status'  => true,
        'comment' => $comment
    ]);
}




    public function destroy(Request $request, Comment $comment) {
        $this->authorize('delete', $comment);
        $comment->delete();
        return response()->json(['status'=>true]);
    }
}
