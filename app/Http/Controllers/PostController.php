<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Post;
use App\Models\PostView;
use App\Models\PostReaction;
use App\Models\PostComment;
use Illuminate\Support\Str;
use App\Models\Message;
use App\Models\PostMedia;
use App\Models\HiddenPost;
use App\Models\PostDownload;
use App\Models\PostSave;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;





class PostController extends Controller
{
    
public function store(Request $request)
{

        $request->validate([
            'content' => 'nullable|string',
            'images' => 'nullable|array',
            'images.*' => 'image|mimes:jpg,jpeg,png,webp|max:5120',
            'video' => 'nullable|file|mimes:mp4,mov|max:51200',
        ]);


    // empty post show
    if (
        !$request->content &&
        !$request->hasFile('images') &&
        !$request->hasFile('video')
    ) {
        return response()->json(['message' => 'Post is empty'], 422);
    }

    // ❌ block image + video together
    if ($request->hasFile('images') && $request->hasFile('video')) {
        return response()->json([
            'message' => 'You can upload images OR a video, not both.'
        ], 422);
    }

    $post = Post::create([
        'user_id' => auth()->id(),
        'content' => $request->content,
    ]);

    // ✅ save images
    if ($request->hasFile('images')) {
        foreach ($request->file('images') as $index => $image) {
            $path = $image->store('posts/images', 'public');

            $post->media()->create([
                'type' => 'image',
                'path' => $path,
                'order' => $index,
            ]);
        }
    }

    // ✅ save ONE video
    if ($request->hasFile('video')) {
        $path = $request->file('video')->store('posts/videos', 'public');

        $post->media()->create([
            'type' => 'video',
            'path' => $path,
            'order' => 0,
        ]);
    }

    return response()->json([
        'post' => $post->load('media')
    ], 201);
}


   public function index()
{
    $posts = Post::whereDoesntHave('hiddenBy', function ($q) {
        $q->where('user_id', auth()->id())
          ->where('hidden_until', '>', now());
    })
    ->with([
        'user:id,first_name,last_name,image',
        'reactions',
        'comments',
        'media'
    ])
    ->withCount(['reactions', 'comments', 'shares']) // 👈 add this
    ->latest()
    ->get()
    ->map(function ($post) {
        return [
            'id' => $post->id,
            'content' => $post->content,

            'media' => $post->media->map(fn ($m) => [
                'id' => $m->id,
                'type' => $m->type,
                'url' => asset('storage/' . $m->path),
            ]),

            'created_at' => $post->created_at->diffForHumans(),

            'user' => [
                'id' => $post->user->id,
                'name' => $post->user->first_name.' '.$post->user->last_name,
                'role' => $post->user->role,
            ],

            'reactions_count' => $post->reactions_count,
            'comments_count'  => $post->comments_count,
            'shares_count'    => $post->shares_count, // 👈 now included
        ];
    });

    return response()->json([
        'status' => true,
        'posts' => $posts
    ]);
}
        

public function show(Post $post)
{
    $post->load([
        'user:id,first_name,last_name,image',
        'media',
        'reactions',
        'comments.user',
        'comments.replies.user',
    ]);

    return response()->json([
        'post' => [
            'id' => $post->id,
            'content' => $post->content,
            'created_at' => $post->created_at->diffForHumans(),

            'user' => [
                'id' => $post->user->id,
                'name' => $post->user->first_name.' '.$post->user->last_name,
                'role' => $post->user->role,

            ],

            'media' => $post->media->map(fn ($m) => [
                'id' => $m->id,
                'type' => $m->type,
                'url' => asset('storage/'.$m->path),
            ]),

            'reactions_count' => $post->reactions->count(),
            'comments_count' => $post->comments->count(),
        ]
    ]);
}


public function hide(Post $post)
{
    $userId = auth()->id();

    // Hide for 7 days (change as you like)
    $hiddenUntil = Carbon::now()->addDays(7);

    HiddenPost::updateOrCreate(
        [
            'user_id' => $userId,
            'post_id' => $post->id,
        ],
        [
            'hidden_until' => $hiddenUntil,
        ]
    );

    return response()->json(['message' => 'Post hidden for you']);
}


public function downloadVideo(Request $request, $postId)
{
    $user = $request->user();

    $post = Post::with('media')->findOrFail($postId);
    $media = $post->media->firstWhere('type', 'video');

    if (!$media) {
        return response()->json(['error' => 'No video found'], 404);
    }

    if ($user) {
        PostDownload::updateOrCreate([
            'user_id' => $user->id,
            'post_id' => $post->id,
        ]);
    }

    $path = storage_path('app/public/' . $media->path);

    if (!file_exists($path)) {
        return response()->json(['error' => 'File not found'], 404);
    }

    return response()->download($path, 'IPK-video.mp4', [
        'Content-Type' => 'video/mp4'
    ]);
}

public function downloadImage(Request $request, $mediaId)
{
    $user = $request->user();

    $media = PostMedia::where('type', 'image')->findOrFail($mediaId);

    $path = storage_path('app/public/' . $media->path);

    if (!file_exists($path)) {
        return response()->json(['error' => 'File not found'], 404);
    }

    if ($user) {
        PostDownload::updateOrCreate([
            'user_id' => $user->id,
            'post_id' => $media->post_id,
        ]);
    }

    return response()->download($path, 'IPK-image.jpg', [
        'Content-Type' => 'image/jpeg'
    ]);
}


public function save(Post $post)
{
    PostSave::firstOrCreate([
        'user_id' => auth()->id(),
        'post_id' => $post->id,
    ]);

    return response()->json(['status' => true, 'message' => 'Saved']);
}

public function library()
{
    $posts = Post::whereHas('saves', function ($q) {
        $q->where('user_id', auth()->id());
    })
    ->with(['media', 'user:id,first_name,last_name,role']) // 👈 load user
    ->latest()
    ->get();

    return response()->json(['status' => true, 'posts' => $posts]);
}


   public function removeFromLibrary(Post $post)
{
    auth()->user()->library()->detach($post->id);
    return response()->json(['status' => true]);
}


public function share(Post $post)
{
    $userId = auth()->id();

    // Check if already shared
    $alreadyShared = $post->shares()
        ->where('user_id', $userId)
        ->exists();

    if (!$alreadyShared) {
        $post->shares()->create([
            'user_id' => $userId,
        ]);

        // increment counter
        $post->increment('shares_count');
    }

    return response()->json([
        'status' => true,
        'already_shared' => $alreadyShared,
    ]);
}


public function sharePost(Request $request, $chatId)
{
    try {
        $request->validate([
            'type' => 'required|string',
            'message' => 'required|string',
            'post_id' => 'required|exists:posts,id',
        ]);

        if (!auth()->check()) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        $message = Message::create([
            'chat_id' => $chatId,
            'user_id' => auth()->id(),
            'sender_id' => auth()->id(),
            'type' => $request->type,
            'message' => $request->message,
        ]);

        // ✅ increment post share count
        $post = Post::find($request->post_id);
        $post->increment('shares_count');

        return response()->json([
            'status' => true,
            'message' => $message,
            'shares_count' => $post->fresh()->shares_count
        ]);
    } catch (\Throwable $e) {
        \Log::error('sharePost error', [
            'error' => $e->getMessage(),
        ]);

        return response()->json([
            'status' => false,
            'error' => $e->getMessage(),
        ], 500);
    }
}




public function view(Post $post)
{
    $post->increment('views');
    return response()->json(['views' => $post->views]);
}

}
