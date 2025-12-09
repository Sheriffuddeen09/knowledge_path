<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Video;
use Illuminate\Support\Facades\Storage;
 use App\Models\VideoReaction;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;


class VideoController extends Controller
{

   public function index()
{
    $userId = Auth::id(); // logged-in user ID or null

    $videos = Video::with([
            'user:id,first_name,last_name,role',
            'category',
            'reactions.user:id,first_name,last_name,role'
        ])

        ->latest()
        ->paginate(12);

    // Transform each video
    $videos->getCollection()->transform(function ($v) use ($userId) {
        // URLs
        $v->video_url = $v->video_path ? asset('storage/' . $v->video_path) : null;
        $v->thumbnail_url = $v->thumbnail ? asset('storage/' . $v->thumbnail) : null;

        // Reaction counts (computed in PHP)
        $v->reaction_counts = $v->reactions
            ->groupBy('emoji')
            ->map(fn($group) => $group->count())
            ->toArray();

        // Current logged-in user's reaction
        $v->my_reaction = $userId
            ? $v->reactions->firstWhere('user_id', $userId)?->emoji
            : null;

         $v->reacted_users = $v->reactions->map(function ($r) use ($userId) {
            return [
                'id'    => $r->user->id,
                'name'  => $r->user_id === $userId
                    ? 'You'
                    : trim($r->user->first_name . ' ' . $r->user->last_name ),
                'role'  => $r->user->role,
                'emoji' => $r->emoji,
            ];
        })->values();

        return $v;
    });

    return response()->json($videos);
}
 
    // Public: Show single video with comments
    public function show(Video $video)
{
    $userId = Auth::id();

    $video->load(['user:id,first_name,last_name, role', 'category', 'comments.user', 'comments.replies.user', 'reactions']);

    $video->video_url = $video->video_path ? asset('storage/' . $video->video_path) : null;
    $video->thumbnail_url = $video->thumbnail ? asset('storage/' . $video->thumbnail) : null;

    // Reaction summary
    $video->reaction_summary = $video->reactions
        ->groupBy('emoji')
        ->map(fn($group) => $group->count())
        ->toArray();

    // Current logged-in user's reaction
    $video->my_reaction = $userId
        ? $video->reactions->firstWhere('user_id', $userId)?->emoji
        : null;

    return response()->json(['video' => $video]);
}

    // Admin: Create video
    public function store(Request $request)
    {
        $this->authorize('create', Video::class);

        $data = $request->validate([
            'description' => 'nullable|string',
            'category_id' => 'nullable|exists:categories,id',
            'video' => 'required|file|mimetypes:video/mp4,video/quicktime|max:200000',
            'thumbnail' => 'nullable|image|max:2048',
            'is_permissible' => 'required|boolean',
        ]);

        $videoPath = $request->file('video')->store('videos', 'public');
        $thumb = $request->hasFile('thumbnail') ? $request->file('thumbnail')->store('thumbnails', 'public') : null;

        $video = Video::create([
            'user_id' => $request->user()->id,
            'category_id' => $data['category_id'] ?? null,
            'description' => $data['description'] ?? null,
            'video_path' => $videoPath,
            'thumbnail' => $thumb,
            'is_public' => true,
            'is_permissible' => $data['is_permissible'],
        ]);

        return response()->json(['status' => true, 'video' => $video], 201);
    }

    // Admin: Update video
    public function update(Request $request, Video $video)
    {
        $this->authorize('update', $video);

        $data = $request->validate([
            'description' => 'nullable|string',
            'category_id' => 'nullable|exists:categories,id',
            'thumbnail' => 'nullable|image|max:2048',
        ]);

        if ($request->hasFile('thumbnail')) {
            Storage::disk('public')->delete($video->thumbnail);
            $data['thumbnail'] = $request->file('thumbnail')->store('thumbnails', 'public');
        }

        $video->update($data);

        return response()->json(['status' => true, 'video' => $video]);
    }

    // Admin: Delete video
    public function destroy(Video $video)
    {
        $this->authorize('delete', $video);

        Storage::disk('public')->delete($video->video_path);
        Storage::disk('public')->delete($video->thumbnail);
        $video->delete();

        return response()->json(['status' => true]);
    }

    // Download video
    public function download(Video $video)
    {
        $path = storage_path('app/public/' . $video->video_path);
        return response()->download($path, $video->title . '.' . pathinfo($video->video_path, PATHINFO_EXTENSION));
}

    // Save video to user's library
    public function saveToLibrary(Request $request, Video $video)
    {
        $request->user()->library()->syncWithoutDetaching([$video->id]);
        return response()->json(['status' => true]);
    }

    // Remove video from library
    public function removeFromLibrary(Request $request, Video $video)
    {
        $request->user()->library()->detach($video->id);
        return response()->json(['status' => true]);
    }

    // React with emoji
    public function react(Request $request, Video $video)
    {
        $data = $request->validate([
            'emoji' => 'required|string|max:50',
        ]);

        $video->reactions()->updateOrCreate(
            ['user_id' => $request->user()->id],
            ['emoji' => $data['emoji']]
        );

        return response()->json(['status' => true]);
    }

    // Comment on video
    public function comment(Request $request, Video $video)
    {
        $data = $request->validate([
            'comment' => 'required|string',
            'emoji' => 'nullable|string|max:50',
        ]);

        $comment = $video->comments()->create([
            'user_id' => $request->user()->id,
            'comment' => $data['comment'],
            'emoji' => $data['emoji'] ?? null,
        ]);

        return response()->json(['status' => true, 'comment' => $comment]);
    }

    // Reply to comment
    public function reply(Request $request, $commentId)
    {
        $data = $request->validate([
            'reply' => 'required|string',
            'emoji' => 'nullable|string|max:50',
        ]);

        $comment = \App\Models\Comment::findOrFail($commentId);

        $reply = $comment->replies()->create([
            'user_id' => $request->user()->id,
            'reply' => $data['reply'],
            'emoji' => $data['emoji'] ?? null,
        ]);

        return response()->json(['status' => true, 'reply' => $reply]);
    }
}
