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
            'visibility' => 'required|in:public,friends,private',
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
            'visibility' => $request->visibility, // 👈 IMPORTANT
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


    
// public function index()
// {
//     $posts = Post::whereDoesntHave('hiddenBy', function ($q) {
//         $q->where('user_id', auth()->id())
//           ->where('hidden_until', '>', now());
//     })
//     ->with([
//         'user:id,first_name,last_name,image',
//         'media',
//     ])
//     ->withCount(['reactions', 'comments', 'shares'])
//     ->inRandomOrder()   // ✅ RANDOM
//     ->get()
//     ->map(function ($post) {
//         return [
//             'id' => $post->id,
//             'content' => $post->content,
//             'media' => $post->media->map(fn ($m) => [
//                 'id' => $m->id,
//                 'type' => $m->type,
//                 'url' => asset('storage/' . $m->path),
//             ]),
//             'created_at' => $post->created_at->diffForHumans(),
//             'user' => [
//                 'id' => $post->user->id,
//                 'name' => $post->user->first_name.' '.$post->user->last_name,
//                 'role' => $post->user->role,
//             ],
//             'reactions_count' => $post->reactions_count,
//             'comments_count'  => $post->comments_count,
//             'shares_count'    => $post->shares_count,
//         ];
//     });

//     return response()->json([
//         'status' => true,
//         'posts' => $posts
//     ]);
// }

public function index()
{
    $friendIds = auth()->user()->allFriendIds()->toArray();

    $viewedPostIds = PostView::where('user_id', auth()->id())
        ->pluck('post_id')
        ->toArray();

    $posts = Post::whereNotIn('id', $viewedPostIds) // 🔥 THIS WAS MISSING
        ->where(function ($query) use ($friendIds) {

            // PUBLIC
            $query->where('visibility', 'public')

            // PRIVATE (only owner)
            ->orWhere(function ($q) {
                $q->where('visibility', 'private')
                  ->where('user_id', auth()->id());
            })

            // FRIENDS
            ->orWhere(function ($q) use ($friendIds) {
                $q->where('visibility', 'friends')
                  ->where(function ($sub) use ($friendIds) {
                      $sub->where('user_id', auth()->id())
                          ->orWhereIn('user_id', $friendIds);
                  });
            });

        })
        ->with([
            'user:id,first_name,last_name,image',
            'media',
            'originalPost.user',
            'originalPost.media'
        ])
        ->withCount([
            'reactions',
            'comments',
            'shares',
            'reposts'
        ])
        ->latest()
        ->get()
        ->map(function ($post) {

            $isRepost = !is_null($post->original_post_id);
            $basePost = $isRepost ? $post->originalPost : $post;

            return [
                'id' => $post->id,
                'is_repost' => $isRepost,
                'original_post_id' => $post->original_post_id, // 🔥 ADD THIS

                'reposted_by' => $isRepost ? [
                    'id' => $post->user->id,
                    'name' => $post->user->first_name.' '.$post->user->last_name,
                ] : null,

                'content' => $basePost->content,

                'media' => $basePost->media->map(fn ($m) => [
                    'id' => $m->id,
                    'type' => $m->type,
                    'url' => asset('storage/' . $m->path),
                ]),

                'user' => [
                    'id' => $basePost->user->id,
                    'name' => $basePost->user->first_name.' '.$basePost->user->last_name,
                ],

                'created_at' => $post->created_at->diffForHumans(),
                'reactions_count' => $basePost->reactions_count ?? 0,
                'comments_count'  => $basePost->comments_count ?? 0,
                'shares_count'    => $basePost->shares_count ?? 0,
                'reposts_count'   => $basePost->reposts_count ?? 0,
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




// public function view(Post $post)
// {
//     $post->increment('views');
//     return response()->json(['views' => $post->views]);
// } 


public function addView($id)
{
    $post = Post::findOrFail($id);

    $alreadyViewed = PostView::where('post_id', $post->id)
        ->where('user_id', auth()->id())
        ->exists();

    if (!$alreadyViewed) {
        PostView::create([
            'post_id' => $post->id,
            'user_id' => auth()->id(),
        ]);

        $post->increment('views');
    }

    return response()->json(['status' => true]);
}


public function myPosts()
{
    $userId = auth()->id();

    $posts = Post::where('user_id', $userId)
        ->with([
            'user:id,first_name,last_name,role,image',
            'media'
        ])
        ->withCount(['reactions', 'comments', 'shares'])
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
                    'name' => $post->user->first_name . ' ' . $post->user->last_name,
                    'role' => $post->user->role,
                ],

                'reactions_count' => $post->reactions_count,
                'comments_count'  => $post->comments_count,
                'shares_count'    => $post->shares_count,
            ];
        });

    return response()->json([
        'status' => true,
        'posts' => $posts
    ]);
}

public function userPosts($id)
{
    $posts = Post::where('user_id', $id)
        ->with([
            'user:id,first_name,last_name,role',
            'media'
        ])
        ->withCount(['reactions', 'comments', 'shares'])
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
                    'name' => $post->user->first_name . ' ' . $post->user->last_name,
                    'role' => $post->user->role,
                ],

                'reactions_count' => $post->reactions_count,
                'comments_count'  => $post->comments_count,
                'shares_count'    => $post->shares_count,
            ];
        });

    return response()->json([
        'status' => true,
        'posts' => $posts
    ]);
}


public function destroy(Post $post)
{
    if ($post->user_id !== auth()->id()) {
        return response()->json(['message' => 'Unauthorized'], 403);
    }

    // Optionally delete media files here

    $post->delete();

    return response()->json([
        'status' => true,
        'message' => 'Post deleted'
    ]);
}

public function update(Request $request, Post $post)
{
    if ($post->user_id !== auth()->id()) {
        return response()->json(['message' => 'Unauthorized'], 403);
    }

    $request->validate([
        'content' => 'required|string'
    ]);

    $post->update([
        'content' => $request->content
    ]);

    return response()->json([
        'status' => true,
        'post' => $post
    ]);
}

public function destroyImage($id)
{
    $media = PostMedia::findOrFail($id);

    if ($media->post->user_id !== auth()->id()) {
        return response()->json(['message' => 'Unauthorized'], 403);
    }

    $post = $media->post;

    // Delete file
    \Storage::disk('public')->delete($media->path);

    // Delete media record
    $media->delete();

    // 🔥 Reload media relationship
    $post->load('media');

    // 🔥 If no media AND no content → delete post
    if ($post->media->count() === 0 && empty($post->content)) {
        $post->delete();

        return response()->json([
            'status' => true,
            'media_id' => $id,
            'post_deleted' => true,
            'post_id' => $post->id
        ]);
    }

    return response()->json([
        'status' => true,
        'media_id' => $id,
        'post_deleted' => false,
        'post_id' => $post->id
    ]);
}

public function repost($id)
{
    $original = Post::findOrFail($id);

    if ($original->user_id === auth()->id()) {
        return response()->json([
            'status' => false,
            'message' => 'You cannot repost your own post'
        ], 403);
    }

    $existing = Post::where('user_id', auth()->id())
        ->where('original_post_id', $original->id)
        ->first();

    if ($existing) {
        return response()->json([
            'status' => false,
            'message' => 'Already reposted'
        ], 400);
    }

    $repost = Post::create([
        'user_id' => auth()->id(),
        'original_post_id' => $original->id,
        'visibility' => 'public',
    ]);

    return response()->json([
        'status' => true,
        'repost_id' => $repost->id
    ]);
}

// public function undoRepost($id)
// {
//     $repost = Post::where('id', $id)
//         ->where('user_id', auth()->id())
//         ->whereNotNull('original_post_id')
//         ->first();

//     if ($repost) {
//         $repost->delete();
//     }

//     return response()->json([
//         'status' => true
//     ]);
// }

public function undoRepost(Post $post)
{

    $post->delete();

    return response()->json([
        'status' => true,
        'message' => 'Post deleted'
    ]);
}
}

