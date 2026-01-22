<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Video;
use Illuminate\Support\Facades\Storage;
 use App\Models\VideoReaction;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\VideoDownload;
use Carbon\Carbon;
use Illuminate\Support\Facades\URL;


class VideoController extends Controller
{

  public function index()
{
    $userId = Auth::id(); // logged-in user ID or null

    $videos = Video::with([
        'user:id,first_name,last_name,role',
        'reactions.user:id,first_name,last_name,role'
    ])
    ->withCount(['comments', 'views']) // 👈 ADD views
    ->latest()
    ->paginate(12);


    // Transform each video
    $videos->getCollection()->transform(function ($v) use ($userId) {
    $v->video_url = $v->video_path ? asset('storage/' . $v->video_path) : null;
    $v->thumbnail_url = $v->thumbnail ? asset('storage/' . $v->thumbnail) : null;

    // 👇 FIX: human readable timestamp
    $v->time_ago = $v->created_at
        ? Carbon::parse($v->created_at)->diffForHumans()
        : null;

    $v->reaction_counts = $v->reactions
        ->groupBy('emoji')
        ->map(fn($group) => $group->count())
        ->toArray();

    $v->my_reaction = $userId
        ? $v->reactions->firstWhere('user_id', $userId)?->emoji
        : null;

    $v->reacted_users = $v->reactions->map(function ($r) use ($userId) {
        return [
            'id'    => $r->user->id,
            'name'  => $r->user_id === $userId
                ? 'You'
                : trim($r->user->first_name . ' ' . $r->user->last_name),
            'role'  => $r->user->role,
            'emoji' => $r->emoji,
            'created_at' => $r->created_at->diffForHumans(),
        ];
    })->values();

    return $v;
});

    return response()->json($videos);
}
public function show(Video $video)
{
    $userId = Auth::id();

    // Ensure comment count is included
    $video = Video::withCount(['comments', 'views']) // 👈 ADD views
    ->with([
        'user:id,first_name,last_name,role',
        'comments.user',
        'comments.replies.user',
        'reactions'
    ])
    ->find($video->id);


    $video->video_url = $video->video_path ? asset('storage/' . $video->video_path) : null;
    $video->thumbnail_url = $video->thumbnail ? asset('storage/' . $video->thumbnail) : null;

    $video->reaction_summary = $video->reactions
        ->groupBy('emoji')
        ->map(fn($group) => $group->count())
        ->toArray();

    $video->my_reaction = $userId
        ? $video->reactions->firstWhere('user_id', $userId)?->emoji
        : null;

    return response()->json(['video' => $video]);
}

    // Admin: Create video
    public function store(Request $request)
    {
        // $this->authorize('create', Video::class);

        $data = $request->validate([
            'description' => 'nullable|string',
            'video' => 'required|file|mimetypes:video/mp4,video/quicktime|max:200000',
            'thumbnail' => 'nullable|image|max:2048',
            'is_permissible' => 'required|boolean',
        ]);

        $videoPath = $request->file('video')->store('videos', 'public');
        $thumb = $request->hasFile('thumbnail') ? $request->file('thumbnail')->store('thumbnails', 'public') : null;

        $video = Video::create([
            'user_id' => $request->user()->id,
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
    public function destroy($id)
{
    $video = Video::findOrFail($id);

    // 🔐 Only owner can delete
    if ($video->user_id !== auth()->id()) {
        return response()->json([
            'message' => 'Unauthorized'
        ], 403);
    }

    // 🗑 Delete video file
    if ($video->video_path && Storage::disk('public')->exists($video->video_path)) {
        Storage::disk('public')->delete($video->video_path);
    }

    // 🗑 Delete thumbnail if exists
    if ($video->thumbnail_path && Storage::disk('public')->exists($video->thumbnail_path)) {
        Storage::disk('public')->delete($video->thumbnail_path);
    }

    // 🗑 Delete DB record
    $video->delete();

    return response()->json([
        'message' => 'Video deleted successfully'
    ]);
}

    // Download video

    public function downloadedVideos(Request $request)
{
    $user = $request->user();

    if (!$user) {
        return response()->json(['error' => 'Not logged in'], 401);
    }

    $downloads = $user->downloads() // now works
        ->with(['video.user'])
        ->get()
        ->map(function ($download) {
            $video = $download->video;
            return [
                'id' => $video->id,
                'title' => $video->title,
                'video_path' => $video->video_path,
                'total_likes' => $video->reactions()->where('type', 'like')->count(),
                'total_comments' => $video->comments()->count(),
                'total_views' => $video->views()->count(),
                'created_at' => $video->created_at->diffForHumans(),
                'user' => $video->user,
            ];
        });

    return response()->json([
        'status' => true,
        'videos' => $downloads
    ]);
}

    // Download a video
    

public function download(Request $request, $id)
{
    $user = $request->user();
    $video = Video::findOrFail($id);

    // Record download (optional but recommended)
    if ($user) {
        $user->library()->syncWithoutDetaching([$video->id]);

        VideoDownload::updateOrCreate([
            'user_id' => $user->id,
            'video_id' => $video->id,
        ]);
    }

    $path = storage_path('app/public/' . $video->video_path);

    if (!file_exists($path)) {
        return response()->json(['error' => 'File not found'], 404);
    }

    // ✅ FORCE DEFAULT DOWNLOAD NAME
    return response()->download($path, 'IPK video.mp4', [
        'Content-Type' => 'video/mp4'
    ]);
}

    // Save video to user's library
    public function savedVideos(Request $request)
{
    $user = $request->user();

    if (!$user) {
        return response()->json(['error' => 'Not logged in'], 401);
    }

    $saved = $user->library()
    ->with(['user'])
    ->withCount([
        'comments as total_comments',
        'views as total_views',
    ])
    ->latest()
    ->get()
    ->map(function ($video) {

        // Get counts for all reactions for this video
        $reactionCounts = DB::table('video_reactions')
            ->where('video_id', $video->id)
            ->select('emoji', DB::raw('count(*) as total'))
            ->groupBy('emoji')
            ->pluck('total','emoji'); // returns ['like' => 2, 'love' => 1, ...]

        return [
            'id' => $video->id,
            'title' => $video->title,
            'views' => $video->total_views ?? 0,
            'reactions' => $reactionCounts, // all emojis
            'total_comments' => $video->total_comments ?? 0,
            'created_at' => $video->created_at->diffForHumans(),
            'user' => $video->user,
            'video_url' => url('storage/' . $video->video_path),
        ];
    });

    return response()->json([
        'status' => true,
        'videos' => $saved
    ]);
}


public function userVideoCount()
{
    $user = Auth::user();

    $count = $user->videos()->count(); // assuming User has `videos()` relation

    return response()->json([
        'video_count' => $count
    ]);
}

public function saveToLibrary(Request $request, Video $video)
{
    $request->user()->library()->syncWithoutDetaching([$video->id]);

    return response()->json(['status' => true, 'message' => 'Video saved']);
}

    public function removeFromLibrary(Video $video)
{
    auth()->user()->library()->detach($video->id);
    return response()->json(['status' => true]);
}

public function reportVideo(Video $video)
{
    // Create a report entry
    $video->reports()->create([
        'user_id' => auth()->id(),
        'reason' => 'Inappropriate content'
    ]);
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
