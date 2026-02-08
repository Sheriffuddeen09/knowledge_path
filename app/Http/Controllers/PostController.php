<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Post;
use App\Models\PostView;
use App\Models\PostReaction;
use App\Models\PostComment;
use Illuminate\Support\Str;
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
        ->latest()
        ->get()
        ->map(function ($post) {
            return [
                'id' => $post->id,
                'content' => $post->content,

                'media' => $post->media->map(fn ($m) => [
                    'id' => $m->id,
                    'type' => $m->type, // image | video
                    'url' => asset('storage/' . $m->path),
                ]),

                'created_at' => $post->created_at->diffForHumans(),

                'user' => [
                    'id' => $post->user->id,
                    'name' => $post->user->first_name.' '.$post->user->last_name,
                    'role' => $post->user->role,
                ],

                'reactions_count' => $post->reactions->count(),
                'comments_count' => $post->comments->count(),
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


public function download(Request $request, $id)
{
    try {
        Log::info("Download start", ['post_id' => $id]);

        $user = $request->user();
        $post = Post::with('media')->findOrFail($id);

        if ($user) {
            PostDownload::updateOrCreate([
                'user_id' => $user->id,
                'post_id' => $post->id,
            ]);
        }

        // get first media (or choose logic you want)
        $media = $post->media->first();

        if (!$media) {
            return response()->json(['error' => 'No media to download'], 400);
        }

        $path = storage_path('app/public/' . $media->path);

        if (!file_exists($path)) {
            return response()->json(['error' => 'File not found', 'path' => $path], 404);
        }

        if ($media->type === 'video') {
            $filename = 'IPK-video.mp4';
            $mime = 'video/mp4';
        } elseif ($media->type === 'image') {
            $filename = 'IPK-image.jpg';
            $mime = 'image/jpeg';
        } else {
            return response()->json(['error' => 'This post cannot be downloaded'], 400);
        }

        return response()->download($path, $filename, [
            'Content-Type' => $mime
        ]);

    } catch (\Throwable $e) {
        Log::error("Download failed", [
            'message' => $e->getMessage(),
        ]);

        return response()->json([
            'error' => 'Download crashed',
            'message' => $e->getMessage(),
        ], 500);
    }
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

}
