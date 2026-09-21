<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Post;
use App\Models\Notification;
use App\Models\PostMedia;
use App\Models\PostDownload;
use App\Models\PostView;
use App\Models\PostReaction;
use App\Models\PostComment;
use Illuminate\Support\Str;
use App\Models\Message;
use App\Models\HiddenPost;
use App\Models\User;
use App\Models\PostSave;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;
use App\Models\Advertisement;
use App\Models\Product;


class PostController extends Controller
{
// downloadVideo
 public function store(Request $request)
{
    $request->validate([
        'content' => 'nullable|string',

        'visibility' => 'required|in:public,friends,private',

        'images' => 'nullable|array',
        'images.*' => 'image|mimes:jpg,jpeg,png,webp|max:5120',

        'video' => 'nullable|file|mimes:mp4,mov|max:51200',

        'trim_start' => 'nullable|numeric|min:0',
        'trim_end' => 'nullable|numeric|gt:trim_start',
    ]);

    if (
        !$request->content &&
        !$request->hasFile('images') &&
        !$request->hasFile('video')
    ) {
        return response()->json([
            'message' => 'Post is empty'
        ], 422);
    }

    if (
        $request->hasFile('images') &&
        $request->hasFile('video')
    ) {
        return response()->json([
            'message' => 'You can upload images OR a video, not both.'
        ], 422);
    }

    if (
        !$request->hasFile('video') &&
        (
            $request->filled('trim_start') ||
            $request->filled('trim_end')
        )
    ) {
        return response()->json([
            'message' => 'Trim values can only be used with a video.'
        ], 422);
    }

    $post = Post::create([
    'user_id' => auth()->id(),
    'content' => $request->content,
    'visibility' => $request->visibility,

    'trim_start' => $request->trim_start,
    'trim_end' => $request->trim_end,

    'is_new_home' => 1,
    'is_new_video' => 0,
        ]);
    

    if ($request->hasFile('images')) {

        foreach (
            $request->file('images')
            as $index => $image
        ) {

            $path = $image->store(
                'posts/images',
                'public'
            );

            $post->media()->create([
                'type' => 'image',
                'path' => $path,
                'order' => $index,
            ]);
        }
    }


    if ($request->hasFile('video')) {

        $video = $request->file('video');

        if (
            !$request->filled('trim_start') ||
            !$request->filled('trim_end')
        ) {

            $path = $video->store(
                'posts/videos',
                'public'
            );

        } else {

            $trimStart = (float) $request->trim_start;
            $trimEnd = (float) $request->trim_end;

            $duration = $trimEnd - $trimStart;

            if ($duration <= 0) {

                return response()->json([
                    'message' => 'Invalid video trim duration.'
                ], 422);
            }

            $originalPath = $video->store(
                'posts/temp',
                'public'
            );

            $inputPath = Storage::disk('public')
                ->path($originalPath);

            $outputDirectory = 'posts/videos';

            Storage::disk('public')
                ->makeDirectory($outputDirectory);

            $outputFilename =
                'post_' .
                uniqid() .
                '_' .
                time() .
                '.mp4';

            $outputRelativePath =
                $outputDirectory .
                '/' .
                $outputFilename;

            $outputPath = Storage::disk('public')
                ->path($outputRelativePath);

            $process = new Process([
                'ffmpeg',

                '-ss',
                (string) $trimStart,

                '-i',
                $inputPath,

                '-t',
                (string) $duration,

                '-c:v',
                'libx264',

                '-preset',
                'fast',

                '-crf',
                '23',

                '-c:a',
                'aac',

                '-movflags',
                '+faststart',

                '-y',

                $outputPath,
            ]);

            $process->setTimeout(300);

            try {

                $process->mustRun();

            } catch (\Throwable $e) {

                Storage::disk('public')
                    ->delete($originalPath);

                $post->delete();

                return response()->json([
                    'message' =>
                        'Video processing failed.',
                    'error' =>
                        config('app.debug')
                            ? $e->getMessage()
                            : null,
                ], 500);
            }

            Storage::disk('public')
                ->delete($originalPath);

            $path = $outputRelativePath;
        }

        $post->media()->create([
            'type' => 'video',
            'path' => $path,
            'order' => 0,
        ]);
    }

    $hasVideo = $post
        ->media()
        ->where('type', 'video')
        ->exists();

    $hasImage = $post
        ->media()
        ->where('type', 'image')
        ->exists();

    $hasContent = !empty($request->content);

    $isVideoOnly =
        $hasVideo &&
        !$hasImage &&
        !$hasContent;

    $post->update([
        'is_new_home' => 1,
        'is_new_video' =>
            $isVideoOnly ? 1 : 0,
    ]);

    return response()->json([
        'post' => $post->load('media')
    ], 201);
}



public function index(Request $request)
{
    $user = $request->user();

    $friendIds = $user->allFriendIds()->toArray();
 

    $isRefresh = $request->boolean('refresh');

    $viewedPostIds = [];

    if (!$isRefresh) {
        $viewedPostIds = PostView::where('user_id', $user->id)
            ->pluck('post_id')
            ->toArray();
    }
 
    
    $postsQuery = Post::query()

    ->where('post_type', '!=', 'reel')

    // Only posts with an existing user
    ->whereHas('user')

    ->when(
        !$isRefresh,
        function ($query) use ($viewedPostIds) {
            $query->whereNotIn(
                'id',
                $viewedPostIds
            );
        }
    )

    ->where(function ($query) use ($friendIds, $user) {

        // PUBLIC
        $query->where(
            'visibility',
            'public'
        )

        // PRIVATE - owner only
        ->orWhere(function ($q) use ($user) {

            $q->where(
                'visibility',
                'private'
            )
            ->where(
                'user_id',
                $user->id
            );

        })

        // FRIENDS
        ->orWhere(function ($q) use (
            $friendIds,
            $user
        ) {

            $q->where(
                'visibility',
                'friends'
            )

            ->where(function ($sub) use (
                $friendIds,
                $user
            ) {

                $sub->where(
                    'user_id',
                    $user->id
                )
                ->orWhereIn(
                    'user_id',
                    $friendIds
                );

            });

        });

    })

    ->with([

        'user:id,first_name,last_name,image',

        'media',

        'advertisement',

        'originalPost' => function ($query) {

            $query->withCount([
                'reactions',
                'comments',
                'shares',
                'reposts',
            ]);

        },

        'originalPost.user:id,first_name,last_name,image',

        'originalPost.media',

    ])

    ->withCount([
        'reactions',
        'comments',
        'shares',
        'reposts',
    ])

    ->inRandomOrder();

    $posts = $postsQuery
    ->get()
    ->map(function ($post) {

        $isRepost = !is_null(
            $post->original_post_id
        );

        $basePost = $post->original_post_id
            ? $post->rootOriginal()
            : $post;

        /*
        |--------------------------------------------------------------------------
        | Safety checks
        |--------------------------------------------------------------------------
        */

        if (!$basePost) {
            return null;
        }

        if (!$post->user) {
            return null;
        }

        if (!$basePost->user) {
            return null;
        }

        /*
        |--------------------------------------------------------------------------
        | Advertisement
        |--------------------------------------------------------------------------
        */

        $advertisement = $post->advertisement
            ? [
                'id' =>
                    $post->advertisement->id,

                'type' =>
                    $post->advertisement->type,
            ]
            : null;

        /*
        |--------------------------------------------------------------------------
        | Reposted By
        |--------------------------------------------------------------------------
        */

        $repostedBy = $isRepost
            ? [
                'id' =>
                    $post->user->id,

                'name' =>
                    trim(
                        $post->user->first_name .
                        ' ' .
                        $post->user->last_name
                    ),

                'image' =>
                    $post->user->image,
            ]
            : null;

        /*
        |--------------------------------------------------------------------------
        | Base User
        |--------------------------------------------------------------------------
        */

        $postUser = [
            'id' =>
                $basePost->user->id,

            'name' =>
                trim(
                    $basePost->user->first_name .
                    ' ' .
                    $basePost->user->last_name
                ),

            'image' =>
                $basePost->user->image,
        ];

        /*
        |--------------------------------------------------------------------------
        | Media
        |--------------------------------------------------------------------------
        */

        $media = $basePost->media
            ->map(function ($m) {

                return [
                    'id' => $m->id,

                    'type' => $m->type,

                    'url' => asset(
                        'storage/' . $m->path
                    ),
                ];

            })
            ->values();

        /*
        |--------------------------------------------------------------------------
        | Return
        |--------------------------------------------------------------------------
        */

        return [

            'id' =>
                $post->id,

            'feed_type' =>
                'post',

            'is_repost' =>
                $isRepost,

            'original_post_id' =>
                $post->original_post_id,

            'is_advertisement' =>
                !is_null(
                    $post->advertisement_id
                ),

            'advertisement' =>
                $advertisement,

            'reposted_by' =>
                $repostedBy,

            'content' =>
                $basePost->content,

            'media' =>
                $media,

            'user' =>
                $postUser,

            'created_at' =>
                $post->created_at
                    ? $post->created_at->diffForHumans()
                    : null,

            'original_created_at' =>
                $basePost->created_at
                    ? $basePost->created_at->diffForHumans()
                    : null,

            'reactions_count' =>
                $basePost->reactions_count ?? 0,

            'comments_count' =>
                $basePost->comments_count ?? 0,

            'shares_count' =>
                $basePost->shares_count ?? 0,

            'reposts_count' =>
                $basePost->reposts_count ?? 0,

        ];

    })
    ->filter()
    ->values();
 
    $products = Product::query()

        ->where('visibility_unlocked', true)

        ->whereNotNull('visibility')

        ->where(function ($query) {

            $query
                ->whereNull('visibility_expires_at')
                ->orWhere(
                    'visibility_expires_at',
                    '>',
                    now()
                );

        })

        ->with([
            'images',
            'user:id,first_name,last_name,image',
        ])

        ->withCount('reviews')
 

        ->inRandomOrder()

        ->limit(10)

        ->get()

        ->map(function ($product) {

            $price = (float) $product->price;

            $discount = (float) $product->discount;

            /*
            |--------------------------------------------------------------------------
            | Discount is subtracted directly from price
            |--------------------------------------------------------------------------
            */

            $finalPrice = max(
                0,
                $price - $discount
            );

            return [
                'id' => $product->id,

                'feed_type' => 'product',

                'title' => $product->title,

                'description' => $product->description,

                'price' => $price,

                'discount' => $discount,

                'final_price' => $finalPrice,

                'currency' => $product->currency,

                'reviews_count' =>
                    $product->reviews_count ?? 0,

                'visibility' =>
                    $product->visibility,

                'visibility_unlocked' =>
                    (bool) $product->visibility_unlocked,

                'visibility_started_at' =>
                    $product->visibility_started_at,

                'visibility_expires_at' =>
                    $product->visibility_expires_at,

                'user' => $product->user
                    ? [
                        'id' => $product->user->id,
                        'name' =>
                            $product->user->first_name . ' ' .
                            $product->user->last_name,
                        'image' =>
                            $product->user->image,
                    ]
                    : null,

                'images' => $product->images
                ->map(function ($image) {
                    return [
                        'id' => $image->id,
                        'url' => asset(
                            'storage/' . $image->image_path
                        ),
                    ];
                })
                ->values(),
            ];
        })
        ->values();
 

    return response()->json([
        'status' => true,

        'posts' => $posts,

        'products' => $products,

        'posts_count' => $posts->count(),

        'products_count' => $products->count(),

        'is_refresh' => $isRefresh,
    ]);
}

public function show($id)
{
    $post = Post::with([
        'user:id,first_name,last_name,image',
        'media',
        'reactions',
        'comments.user',
        'comments.replies.user',
        'originalPost.user',
        'originalPost.media',
    ])
    ->withCount([
        'reactions',
        'comments',
        'shares',
        'reposts',
    ])
    ->where('post_type', '!=', 'reel')
    ->findOrFail($id);

    $post->media->transform(function ($media) {
        $media->url = $media->url
            ?? ($media->path
                ? asset('storage/' . $media->path)
                : null);

        return $media;
    });

    return response()->json([
        'post' => $post,
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


public function downloadReel(Request $request, $mediaId)
{
    $user = $request->user();

    // Get the EXACT media item selected by the user
    $media = PostMedia::findOrFail($mediaId);

    // Make sure this media is actually a video
    if ($media->type !== 'video') {
        return response()->json([
            'error' => 'This media is not a video.'
        ], 404);
    }

    // Get the parent post
    $post = Post::findOrFail($media->post_id);

    // Optional: record show
    if ($user) {
        PostDownload::updateOrCreate(
            [
                'user_id' => $user->id,
                'post_id' => $post->id,
            ],
            [
                'updated_at' => now(),
            ]
        );
    }

    // Physical file location
    $path = storage_path(
        'app/public/' . $media->path
    );

    if (!file_exists($path)) {
        return response()->json([
            'error' => 'Video file not found.',
            'path' => $media->path,
        ], 404);
    }

    return response()->download(
        $path,
        'IPK-video-' . $media->id . '.mp4',
        [
            'Content-Type' => 'video/mp4',
        ]
    );
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
        ->withCount([
                'reactions',
                'comments',
                'shares',
                'reposts',
                'views',
            ])
        ->latest()
        ->get()

       
        ->filter(function ($post) {

            if ($post->post_type === 'reel') {
                return $post->reel_type === 'video';
            }

            return true;
        })

        ->filter(function ($post) {

            if (
                $post->post_type === 'reel' &&
                $post->reel_type === 'video'
            ) {
                return $post->media
                    ->where('type', 'video')
                    ->isNotEmpty();
            }

            return true;
        })

        ->map(function ($post) {

            $media = $post->post_type === 'reel'
                ? $post->media
                    ->where('type', 'video')
                    ->values()
                : $post->media->values();

            return [
                'id' => $post->id,

                'post_type' => $post->post_type,

                'reel_type' => $post->reel_type,

                'content' => $post->content,

                'media' => $media
                    ->map(function ($m) {
                        return [
                            'id' => $m->id,
                            'type' => $m->type,
                            'url' => asset('storage/' . $m->path),
                        ];
                    })
                    ->values(),

                'created_at' => $post->created_at->diffForHumans(),

                'user' => [
                    'id' => $post->user->id,
                    'name' => $post->user->first_name . ' ' . $post->user->last_name,
                    'role' => $post->user->role,
                ],

                'reactions_count' => $post->reactions_count,
                'comments_count'  => $post->comments_count,
                'shares_count'    => $post->shares_count,
                'reposts_count'   => $post->reposts_count ?? 0,
                'views'           => $post->views_count,
            ];
        })
        ->values();

    return response()->json([
        'status' => true,
        'posts' => $posts
    ]);
}


public function userPosts($id)
{
    $posts = Post::where('user_id', $id)
        ->with([
            'user:id,first_name,last_name,role,image',
            'media'
        ])
        ->withCount([
                'reactions',
                'comments',
                'shares',
                'reposts',
                'views',
            ])
        ->latest()
        ->get()

       
        ->filter(function ($post) {

            if ($post->post_type === 'reel') {
                return $post->reel_type === 'video';
            }

            return true;
        })

        ->filter(function ($post) {

            if (
                $post->post_type === 'reel' &&
                $post->reel_type === 'video'
            ) {
                return $post->media
                    ->where('type', 'video')
                    ->isNotEmpty();
            }

            return true;
        })

        ->map(function ($post) {

          
            $media = $post->post_type === 'reel'
                ? $post->media
                    ->where('type', 'video')
                    ->values()
                : $post->media->values();

            return [
                'id' => $post->id,

                'post_type' => $post->post_type,

                'reel_type' => $post->reel_type,

                'content' => $post->content,

                'media' => $media
                    ->map(function ($m) {
                        return [
                            'id' => $m->id,
                            'type' => $m->type,
                            'url' => asset('storage/' . $m->path),
                        ];
                    })
                    ->values(),

                'created_at' => $post->created_at->diffForHumans(),

                'user' => [
                    'id' => $post->user->id,
                    'name' => $post->user->first_name . ' ' . $post->user->last_name,
                    'role' => $post->user->role,
                ],

                'reactions_count' => $post->reactions_count,
                'comments_count'  => $post->comments_count,
                'shares_count'    => $post->shares_count,
                'reposts_count'   => $post->reposts_count ?? 0,
                'views'           => $post->views_count,
            ];
        })
        ->values();

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
 


public function repost(Request $request, $postId)
{
    $user = $request->user();

    $post = Post::findOrFail($postId);
 
    if ((int) $post->user_id === (int) $user->id) {
        return response()->json([
            'message' => 'You cannot repost your own post',
        ], 422);
    }
 
    $validated = $request->validate([
        'visibility' => [
            'required',
            'in:public,friends',
        ],
    ]);

    $existingRepost = Post::where('user_id', $user->id)
        ->where('original_post_id', $post->id)
        ->first();

    if ($existingRepost) {
        return response()->json([
            'message' => 'You have already reposted this post.',
            'repost' => $existingRepost,
        ], 422);
    }

    $repost = Post::create([
        'user_id' => $user->id,

        'original_post_id' => $post->id,

        'content' => null,

        'visibility' => $validated['visibility'],

        'post_type' => 'repost',
    ]);
 
    $reposterName = trim(
        $user->first_name . ' ' . $user->last_name
    );

    $notification = Notification::where('user_id', $post->user_id)
        ->where('type', 'post_repost')
        ->whereJsonContains('data->post_id', $post->id)
        ->first();

    if ($notification) {

        $data = json_decode(
            $notification->data,
            true
        );

        $reposters = collect(
            $data['reposters'] ?? []
        );

        if (!$reposters->contains($reposterName)) {
            $reposters->push($reposterName);
        }

        $notification->update([
            'data' => json_encode([
                'post_id' => $post->id,
                'reposters' => $reposters
                    ->values()
                    ->toArray(),
            ]),
            'read' => false,
        ]);

    } else {

        Notification::create([
            'user_id' => $post->user_id,
            'type' => 'post_repost',

            'data' => json_encode([
                'post_id' => $post->id,
                'reposters' => [
                    $reposterName,
                ],
            ]),

            'redirect_url' => "/repost/{$post->id}",

            'read' => false,
        ]);
    }
 
    $repost->load([
        'user:id,first_name,last_name,image',
        'originalPost.user:id,first_name,last_name,image',
        'originalPost.media',
    ]);
 
    return response()->json([
        'message' => 'Post reposted successfully',

        'repost' => [
            'id' => $repost->id,

            'feed_type' => 'post',

            'is_repost' => true,

            'original_post_id' =>
                $repost->original_post_id,

            'reposted_by' => [
                'id' => $repost->user->id,

                'name' =>
                    $repost->user->first_name .
                    ' ' .
                    $repost->user->last_name,
            ],

            'content' =>
                $repost->originalPost->content,

            'media' =>
                $repost->originalPost->media
                    ->map(function ($media) {
                        return [
                            'id' => $media->id,

                            'type' => $media->type,

                            'url' => asset(
                                'storage/' .
                                $media->path
                            ),
                        ];
                    })
                    ->values(),

            'user' => [
                'id' =>
                    $repost->originalPost->user->id,

                'name' =>
                    $repost->originalPost->user->first_name .
                    ' ' .
                    $repost->originalPost->user->last_name,
            ],

            'created_at' =>
                $repost->created_at->diffForHumans(),

            'reactions_count' => 0,

            'comments_count' => 0,

            'shares_count' => 0,

            'reposts_count' => 0,
        ],
    ]);
}


public function Search(Request $request)
{
    $users = User::where('first_name', 'like', "%{$request->q}%")
        ->orWhere('last_name', 'like', "%{$request->q}%")
        ->limit(20)
        ->get(['id', 'first_name', 'last_name', 'role', 'image']);

    return response()->json([
        'users' => $users->map(fn($u) => [
            'id' => $u->id,
            'name' => $u->first_name . ' ' . $u->last_name,
            'role' => $u->role ?? 'user',
            'image' => $u->image 
                ? asset('storage/' . $u->image)
                : null
        ])
    ]);
}


    public function view(Request $request, Post $post)
    {
    try {

        $userId = auth()->id();

        // Prevent counting the same user's view repeatedly
        $alreadyViewed = $post->views()
            ->where('user_id', $userId)
            ->exists();

        if (!$alreadyViewed) {

            $post->views()->create([
                'user_id' => $userId,
            ]);

            $post->increment('views');
        }

        return response()->json([
            'status' => true,
            'viewed' => true,
            'views_count' => $post->fresh()->views,
        ]);

    } catch (\Throwable $e) {

        \Log::error('POST VIEW ERROR', [
            'post_id' => $post->id,
            'user_id' => auth()->id(),
            'error' => $e->getMessage(),
        ]);

        return response()->json([
            'status' => false,
            'message' => 'Unable to record view.',
        ], 500);
    }
}


// created_at
public function indexVideo()
{
    $userId = auth()->id();

    $friendIds = auth()->user()
        ->allFriendIds()
        ->toArray();

    $viewedPostIds = PostView::where('user_id', $userId)
        ->pluck('post_id')
        ->toArray();

    $posts = Post::where('post_type', 'post')
        ->whereNull('advertisement_id')

        ->where(function ($query) use ($friendIds, $userId) {

            // PUBLIC
            $query->where('visibility', 'public')

                // PRIVATE - owner only
                ->orWhere(function ($q) use ($userId) {
                    $q->where('visibility', 'private')
                        ->where('user_id', $userId);
                })

                // FRIENDS
                ->orWhere(function ($q) use ($friendIds, $userId) {
                    $q->where('visibility', 'friends')
                        ->where(function ($sub) use ($friendIds, $userId) {
                            $sub->where('user_id', $userId)
                                ->orWhereIn('user_id', $friendIds);
                        });
                });
        })

        ->whereHas('media', function ($q) {
            $q->where('type', 'video');
        })

        ->with([
            'user:id,first_name,last_name,image,role',
            'media',
            'originalPost.user',
            'originalPost.media',
        ])

        ->withCount([
            'reactions',
            'comments',
            'shares',
            'reposts',
        ])

        ->latest()
        ->get()

        ->map(function ($post) use ($viewedPostIds) {

            $isRepost = !is_null($post->original_post_id);

            $basePost = $post->original_post_id
                ? $post->rootOriginal()
                : $post;

            $viewed = false;

            if (
                $basePost->post_type === 'post' &&
                is_null($basePost->advertisement_id) &&
                $basePost->media->contains('type', 'video')
            ) {
                $viewed = in_array(
                    $basePost->id,
                    $viewedPostIds
                );
            }

            return [
                'id' => $post->id,

                'post_type' => $post->post_type,

                'is_advertisement' => false,

                'advertisement_id' => null,

                'viewed' => $viewed,

                'is_repost' => $isRepost,

                'original_post_id' =>
                    $post->original_post_id,

                'reposted_by' => $isRepost
                    ? [
                        'id' => $post->user->id,

                        'name' =>
                            trim(
                                $post->user->first_name .
                                ' ' .
                                $post->user->last_name
                            ),
                    ]
                    : null,

                'content' => $basePost->content,

                'media' => $basePost->media
                    ->map(function ($m) {
                        return [
                            'id' => $m->id,
                            'type' => $m->type,
                            'url' => asset(
                                'storage/' . $m->path
                            ),
                        ];
                    })
                    ->values(),

                'user' => $basePost->user
                    ? [
                        'id' => $basePost->user->id,

                        'name' =>
                            trim(
                                $basePost->user->first_name .
                                ' ' .
                                $basePost->user->last_name
                            ),

                        'role' =>
                            $basePost->user->role ?? null,

                        'image' =>
                            $basePost->user->image ?? null,
                    ]
                    : null,

                'created_at' => $post->created_at?->toISOString(),
                
                'reactions_count' =>
                    $basePost->reactions_count ?? 0,

                'comments_count' =>
                    $basePost->comments_count ?? 0,

                'shares_count' =>
                    $basePost->shares_count ?? 0,

                'reposts_count' =>
                    $basePost->reposts_count ?? 0,
            ];
        })
        ->values();

    return response()->json([
        'status' => true,
        'posts' => $posts,
    ]);
}
 

public function nextVideo(Request $request, Post $post)
{
    $userId = auth()->id();

    $showAdvertisement = $request->boolean(
        'show_advertisement',
        false
    );

    /*
    |--------------------------------------------------------------------------
    | SHOW ADVERTISEMENT
    |--------------------------------------------------------------------------
    */
    if ($showAdvertisement) {

        $advertisement = $this->getRandomAdvertisement($userId);

        if ($advertisement) {

            $formattedAdvertisement =
                $this->formatAdvertisementPost($advertisement);

            if ($formattedAdvertisement) {

                return response()->json([
                    'status' => true,
                    'type' => 'advertisement',
                    'video' => $formattedAdvertisement,
                    'all_viewed' => false,
                ]);
            }
        }
    }

    /*
    |--------------------------------------------------------------------------
    | NORMAL VIDEOS ONLY
    |--------------------------------------------------------------------------
    |
    | Never include:
    | - reels
    | - advertisement posts
    |
    */
    $nextPost = Post::where('post_type', 'post')
        ->whereNull('advertisement_id')
        ->whereHas('media', function ($q) {
            $q->where('type', 'video');
        })
        ->where('id', '>', $post->id)
        ->whereDoesntHave('views', function ($q) use ($userId) {
            $q->where('user_id', $userId);
        })
        ->orderBy('id', 'asc')
        ->with([
            'user:id,first_name,last_name,role,image',
            'media',
        ])
        ->first();

    /*
    |--------------------------------------------------------------------------
    | NO MORE UNVIEWED VIDEOS
    |--------------------------------------------------------------------------
    |
    | IMPORTANT:
    | Do NOT search backwards here.
    |
    | Searching backwards was causing:
    |
    | Video 13
    |    ↓
    | Video 1
    |
    | instead of showing the reset popup.
    |
    */
    if (!$nextPost) {

        return response()->json([
            'status' => true,
            'type' => 'video',
            'video' => null,
            'all_viewed' => true,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | RETURN NEXT NORMAL VIDEO
    |--------------------------------------------------------------------------
    */
    return response()->json([
        'status' => true,
        'type' => 'video',
        'video' => $this->formatVideoPost(
            $nextPost,
            $userId
        ),
        'all_viewed' => false,
    ]);
}


private function formatVideoPost(
    Post $post,
    $userId = null
) {
    $video = $post->media
        ->where('type', 'video')
        ->first();

    $viewed = false;

    if (
        $userId &&
        $post->post_type === 'post' &&
        is_null($post->advertisement_id)
    ) {
        $viewed = $post->views()
            ->where('user_id', $userId)
            ->exists();
    }

    return [
        'id' => $post->id,

        'post_type' => $post->post_type,

        'is_advertisement' => false,

        'advertisement_id' => null,

        'viewed' => $viewed,

        'content' => $post->content,

        'created_at' =>
            $post->created_at?->diffForHumans(),

        'video' => $video
            ? [
                'id' => $video->id,

                'type' => 'video',

                'url' => asset(
                    'storage/' . $video->path
                ),
            ]
            : null,

        'media' => $video
            ? [
                [
                    'id' => $video->id,

                    'type' => 'video',

                    'url' => asset(
                        'storage/' . $video->path
                    ),
                ],
            ]
            : [],

        'user' => $post->user
            ? [
                'id' => $post->user->id,

                'name' => trim(
                    $post->user->first_name .
                    ' ' .
                    $post->user->last_name
                ),

                'role' => $post->user->role,

                'image' => $post->user->image,
            ]
            : null,
    ];
}


public function previousVideo(Post $post)
{
    $userId = auth()->id();

    $baseQuery = Post::where('post_type', 'post')

        // Exclude advertisements
        ->whereNull('advertisement_id')

        ->whereHas('media', function ($q) {
            $q->where('type', 'video');
        });

    $previousPost = (clone $baseQuery)

        ->where('id', '<', $post->id)

        ->orderBy('id', 'desc')

        ->with([
            'user:id,first_name,last_name,role,image',
            'media',
        ])

        ->first();

    if (!$previousPost) {

        $previousPost = (clone $baseQuery)

            ->where('id', '>', $post->id)

            ->orderBy('id', 'desc')

            ->with([
                'user:id,first_name,last_name,role,image',
                'media',
            ])

            ->first();
    }

    if (!$previousPost) {

        return response()->json([
            'status' => false,
            'video' => null,
        ], 404);
    }

    return response()->json([
        'status' => true,

        'video' => $this->formatVideoPost(
            $previousPost,
            $userId
        ),
    ]);
}



public function resetVideoViews()
{
    $userId = auth()->id();

    PostView::where('user_id', $userId)
        ->whereHas('post', function ($q) {

            $q->where('post_type', 'post')
                ->whereNull('advertisement_id')
                ->whereHas('media', function ($media) {
                    $media->where('type', 'video');
                });

        })
        ->delete();

    return response()->json([
        'status' => true,
        'message' => 'Video views reset successfully.',
    ]);
}

public function viewid(Request $request, Post $post)
{
    if ($post->post_type !== 'post') {
        return response()->json([
            'message' => 'Only normal posts can be viewed as videos.'
        ], 422);
    }

    $user = $request->user();

    $view = PostView::firstOrCreate([
        'post_id' => $post->id,
        'user_id' => $user->id,
    ]);

    return response()->json([
        'message' => 'Video view recorded.',
        'viewed' => true,
        'post_id' => $post->id,
    ]);
}

private function userCanSeeAdvertisement(
    Advertisement $advertisement,
    int $userId
): bool {
    return true;
}

private function getRandomAdvertisement($userId)
{
    $advertisements = Advertisement::query()
        ->where('status', 'approved')
        ->where('visibility_unlocked', true)
        ->whereNotNull('visibility_expires_at')
        ->where(
            'visibility_expires_at',
            '>',
            now()
        )
        ->with([
            'post.user:id,first_name,last_name,image,role',
            'post.media',
        ])
        ->get();

    \Log::info('ADVERTISEMENT CHECK', [
        'user_id' => $userId,
        'count_before_audience_filter' => $advertisements->count(),
        'advertisements' => $advertisements->map(function ($ad) {
            return [
                'id' => $ad->id,
                'status' => $ad->status,
                'visibility_unlocked' =>
                    $ad->visibility_unlocked,
                'audience' => $ad->audience,
                'expires_at' =>
                    $ad->visibility_expires_at,
                'post_id' =>
                    $ad->post?->id,
                'media_count' =>
                    $ad->post?->media?->count(),
            ];
        })->values(),
    ]);

    $advertisements = $advertisements->filter(
        function ($advertisement) use ($userId) {

            if (!$advertisement->post) {
                return false;
            }

            return $this->userCanSeeAdvertisement(
                $advertisement,
                $userId
            );
        }
    );

    \Log::info('ADVERTISEMENT AFTER AUDIENCE FILTER', [
        'user_id' => $userId,
        'count' => $advertisements->count(),
        'ids' => $advertisements->pluck('id')->values(),
    ]);

    if ($advertisements->isEmpty()) {
        return null;
    }

    return $advertisements->random();
}

private function formatAdvertisementPost(
    Advertisement $advertisement
) {
    $post = $advertisement->post;

    if (!$post) {
        return null;
    }

    $media = $post->media->map(function ($m) {
        return [
            'id' => $m->id,
            'type' => $m->type,
            'url' => asset('storage/' . $m->path),
        ];
    })->values();

    return [
        'id' => $post->id,

        'post_type' => 'post',

        'is_advertisement' => true,

        'advertisement_id' =>
            $advertisement->id,

        'advertisement_type' =>
            $advertisement->type,

        'title' =>
            $advertisement->title,

        'description' =>
            $advertisement->description,

        'link' =>
            $advertisement->link,

        'media' =>
            $media,

        'video' =>
            $media->firstWhere('type', 'video'),

        'user' => $post->user
            ? [
                'id' => $post->user->id,

                'name' => trim(
                    $post->user->first_name .
                    ' ' .
                    $post->user->last_name
                ),

                'role' =>
                    $post->user->role,

                'image' =>
                    $post->user->image,
            ]
            : null,

        'created_at' =>
            $post->created_at?->diffForHumans(),

        'reactions_count' =>
            $post->reactions()->count(),

        'comments_count' =>
            $post->comments()->count(),

        'shares_count' =>
            $post->shares()->count(),

        'reposts_count' =>
            $post->reposts()->count(),

        'viewed' => false,

        'expires_at' =>
            $advertisement
                ->visibility_expires_at
                ?->toISOString(),
    ];
} 
}

