<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Models\PostMedia;
use App\Models\ReelView;
use App\Models\Chat;
use App\Models\ReelReaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;
use App\Mail\ReelMessageMail;
use App\Mail\ReelReactionMail;
use Illuminate\Support\Facades\Mail;
use Throwable;


class ReelController extends Controller
{
   

public function store(Request $request)
{
    

    $request->validate([
        'reel_type' => [
            'required',
            Rule::in([
                'video',
                'image',
                'text',
                'mixed',
            ]),
        ],

        'content' => [
            'nullable',
            'string',
            'max:5000',
        ],
        

        'visibility' => [
                'nullable',
                Rule::in([
                    'public',
                    'friends',
                    'private',
                ]),
            ],

        'background_color' => [
            'nullable',
            'string',
            'max:50',
        ],

        'font' => [
            'nullable',
            'string',
            'max:100',
        ],


        'media' => [
            'required_unless:reel_type,text',
            'array',
            'min:1',
        ],

        'media.*.file' => [
            'required',
            'file',
            'max:102400',
        ],

        'media.*.description' => [
            'nullable',
            'string',
            'max:700',
        ],


        'media.*.type' => [
            'required',
            Rule::in([
                'image',
                'video',
            ]),
        ],

        'media.*.duration' => [
            'nullable',
            'numeric',
            'min:0',
        ],

        'media.*.trim_start' => [
            'nullable',
            'numeric',
            'min:0',
        ],

        'media.*.trim_end' => [
            'nullable',
            'numeric',
            'min:0',
        ],
    ]);


    $type = $request->reel_type;

    if ($type === 'text') {

        if (!$request->filled('content')) {

            return response()->json([
                'message' =>
                    'Text is required for a text reel.'
            ], 422);
        }

        if ($request->hasFile('media')) {

            return response()->json([
                'message' =>
                    'Text reels cannot contain media.'
            ], 422);
        }
    }
    $uploadedMedia =
        $request->file('media', []);


    if (
        $type !== 'text' &&
        empty($uploadedMedia)
    ) {

        return response()->json([
            'message' =>
                'At least one image or video is required.'
        ], 422);
    }
    $hasImages = false;
    $hasVideos = false;


    foreach ($uploadedMedia as $index => $media) {

        $mediaType =
            $request->input(
                "media.$index.type"
            );


        if ($mediaType === 'image') {
            $hasImages = true;
        }


        if ($mediaType === 'video') {
            $hasVideos = true;
        }
    }

    if ($type === 'image' && $hasVideos) {

        return response()->json([
            'message' =>
                'An image reel cannot contain videos.'
        ], 422);
    }


    if ($type === 'video' && $hasImages) {

        return response()->json([
            'message' =>
                'A video reel cannot contain images.'
        ], 422);
    }


    if (
        $type === 'image' &&
        !$hasImages
    ) {

        return response()->json([
            'message' =>
                'An image reel requires at least one image.'
        ], 422);
    }


    if (
        $type === 'video' &&
        !$hasVideos
    ) {

        return response()->json([
            'message' =>
                'A video reel requires at least one video.'
        ], 422);
    }


    if (
        $type === 'mixed' &&
        (!$hasImages || !$hasVideos)
    ) {

        return response()->json([
            'message' =>
                'A mixed reel must contain both images and videos.'
        ], 422);
    }

    $totalReelDuration = 0;


    foreach ($uploadedMedia as $index => $media) {

        $mediaType =
            $request->input(
                "media.$index.type"
            );

        if ($mediaType === 'image') {

            $totalReelDuration += 30;

            continue;
        }

        if ($mediaType === 'video') {

            $trimStart = (float) $request->input(
                "media.$index.trim_start",
                0
            );

            $trimEnd = (float) $request->input(
                "media.$index.trim_end"
            );

            $videoDuration =
                $trimEnd - $trimStart;


            if ($videoDuration <= 0) {

                return response()->json([
                    'message' =>
                        "Invalid video duration for media item {$index}."
                ], 422);
            }

            if ($videoDuration > 90) {

                return response()->json([
                    'message' =>
                        'Every video must be trimmed to 1 minute 30 seconds or less.'
                ], 422);
            }


            $totalReelDuration += $videoDuration;
        }
    }

    $visibility = $request->input(
    'visibility'
    ) ?: 'friends';
    
    
    $post = Post::create([
        'user_id' =>
            auth()->id(),

        'post_type' =>
            'reel',

        'reel_type' =>
            $type,

        'content' =>
            $request->content,
            

        'visibility' =>
            $visibility,

        'background_color' =>
            $request->background_color,

        'font' =>
            $request->font,

        'reel_duration' =>
            (int) ceil(
                $totalReelDuration
            ),

        'is_new_home' =>
            0,

        'is_new_video' =>
            1,
    ]);
    foreach (
        $uploadedMedia
        as $index => $media
    ) {

        $file =
            $media['file'] ?? null;


        if (!$file) {
            continue;
        }


        $mediaType =
            $request->input(
                "media.$index.type"
            );

        if ($mediaType === 'image') {

    if (
        !str_starts_with(
            $file->getMimeType(),
            'image/'
        )
    ) {

        $post->delete();

        return response()->json([
            'message' =>
                "Media item {$index} is not a valid image."
        ], 422);
    }

    $path = $file->store(
        'posts/reels/images',
        'public'
    );

    $postMedia = $post->media()->create([
        'type' => 'image',
        'path' => $path,
        'order' => $index,
    ]);

    $description = $request->input(
        "media.$index.description"
    );

    if (
        $description &&
        trim($description) !== ''
    ) {  
        $postMedia->description()->create([
            'type' => 'image',
            'content' => trim($description),
        ]);
    }

    continue;
}
        if ($mediaType === 'video') {

            $trimStart =
                (float) $request->input(
                    "media.$index.trim_start",
                    0
                );

            $trimEnd =
                (float) $request->input(
                    "media.$index.trim_end"
                );


            $duration =
                $trimEnd - $trimStart;

            if ($duration <= 0) {

                $post->delete();

                return response()->json([
                    'message' =>
                        "Invalid trim range for video {$index}."
                ], 422);
            }


            if ($duration > 90) {

                $post->delete();

                return response()->json([
                    'message' =>
                        "Video {$index} must be 90 seconds or less."
                ], 422);
            }

            $originalPath =
                $file->store(
                    'posts/reels/temp',
                    'public'
                );


            $inputPath =
                Storage::disk('public')
                    ->path($originalPath);

            $outputDirectory =
                'posts/reels/videos';


            Storage::disk('public')
                ->makeDirectory(
                    $outputDirectory
                );

            $outputFilename =
                'reel_' .
                uniqid() .
                '_' .
                time() .
                '_' .
                $index .
                '.mp4';


            $outputRelativePath =
                $outputDirectory .
                '/' .
                $outputFilename;


            $outputPath =
                Storage::disk('public')
                    ->path(
                        $outputRelativePath
                    );
            $process =
                new Process([
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

            } catch (Throwable $e) {

                Storage::disk('public')
                    ->delete($originalPath);

                foreach (
                    $post->media as $savedMedia
                ) {

                    if (
                        $savedMedia->path &&
                        Storage::disk('public')
                            ->exists(
                                $savedMedia->path
                            )
                    ) {

                        Storage::disk('public')
                            ->delete(
                                $savedMedia->path
                            );
                    }
                }


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
                ->delete(
                    $originalPath
                );
            $postMedia = $post->media()->create([
                    'type' => 'video',
                    'path' => $outputRelativePath,
                    'order' => $index,
                ]);

                $description = $request->input(
                    "media.$index.description"
                );

                if (
                    $description &&
                    trim($description) !== ''
                ) {

                    $postMedia->description()->create([
                        'type' => 'video',
                        'content' => trim($description),
                    ]);
                }
        }
    }

    $post->load([
        'user',
        'media',
    ]);
    return response()->json([
        'success' =>
            true,

        'message' =>
            'Reel created successfully.',

        'post' =>
            $post,
    ], 201);
}
   
public function index(Request $request)
{
    $user = $request->user();

    $reels = Post::query()
        ->where('post_type', 'reel')
        ->where(
            'created_at',
            '>=',
            now()->subHours(24)
        )
        ->whereIn('visibility', [
            'friends',
        ])

        ->with([
            'user:id,first_name,last_name',

            'media' => function ($query) {

                $query
                    ->select([
                        'id',
                        'post_id',
                        'type',
                        'path',
                        'order',
                    ])
                    ->with([
                        'description:id,post_media_id,type,content',
                    ])
                    ->orderBy('order');
            },
        ])

        // Reel views + total reactions
        ->withCount([
            'reelViews',
            'reelReactions',
        ])

        // Has current user viewed this reel?
        ->withExists([
            'reelViews as has_viewed' => function ($query) use ($user) {

                $query->where(
                    'user_id',
                    $user->id
                );
            },
        ])

        // CURRENT USER'S REACTION
        ->addSelect([
            'user_reaction' => ReelReaction::query()
                ->select('reel_reactions.reaction')
                ->whereColumn(
                    'reel_reactions.post_id',
                    'posts.id'
                )
                ->where(
                    'reel_reactions.user_id',
                    $user->id
                )
                ->limit(1),
        ])

        ->latest()
        ->get();


    /*
    |--------------------------------------------------------------------------
    | FILTER REELS USER CAN SEE
    |--------------------------------------------------------------------------
    */

    $reels = $reels->filter(
        function ($reel) use ($user) {

            // Own reels
            if (
                (int) $reel->user_id ===
                (int) $user->id
            ) {
                return true;
            }

            // Friends reels
            if (
                $reel->visibility === 'friends'
            ) {

                return $this->canSeeReel(
                    $user->id,
                    $reel->user_id
                );
            }

            return false;
        }
    );

    $reels = $reels
        ->map(
            function ($reel) {

                return [

                    'id' =>
                        $reel->id,

                    'reel_type' =>
                        $reel->reel_type,

                    'content' =>
                        $reel->content,

                    'visibility' =>
                        $reel->visibility,

                    'duration' =>
                        $reel->reel_duration,

                    'created_at' =>
                        $reel->created_at,

                    'expires_at' =>
                        $reel->created_at
                            ->copy()
                            ->addHours(24),

                    'has_viewed' =>
                    (bool) $reel->has_viewed,

                    'views_count' =>
                    (int) $reel->reel_views_count,

                    'reactions_count' =>
                    (int) $reel->reel_reactions_count,

                    'user_reaction' =>
                    $reel->user_reaction,
                    'user' => [

                        'id' =>
                            $reel->user->id,

                        'first_name' =>
                            $reel->user->first_name,

                        'last_name' =>
                            $reel->user->last_name,

                        'initial' =>
                            strtoupper(
                                mb_substr(
                                    $reel->user->first_name ?: 'U',
                                    0,
                                    1
                                )
                            ),
                    ],

                    'media' =>
                        $reel->media
                            ->sortBy('order')
                            ->values()
                            ->map(
                                function ($media) {

                                    return [

                                        'id' =>
                                            $media->id,

                                        'type' =>
                                            $media->type,

                                        'url' =>
                                            asset(
                                                'storage/' .
                                                $media->path
                                            ),

                                        'order' =>
                                            $media->order,

                                        'description' =>
                                            $media->description
                                                ? [

                                                    'id' =>
                                                        $media
                                                            ->description
                                                            ->id,

                                                    'type' =>
                                                        $media
                                                            ->description
                                                            ->type,

                                                    'content' =>
                                                        $media
                                                            ->description
                                                            ->content,

                                                ]
                                                : null,
                                    ];
                                }
                            )
                            ->values(),
                ];
            }
        )
        ->values();


    /*
    |--------------------------------------------------------------------------
    | MY REELS
    |--------------------------------------------------------------------------
    */

    $myReels = $reels
        ->filter(
            function ($reel) use ($user) {

                return (int) $reel['user']['id'] ===
                    (int) $user->id;
            }
        )
        ->sortBy('created_at')
        ->values();


    /*
    |--------------------------------------------------------------------------
    | OTHER USERS
    |--------------------------------------------------------------------------
    */

    $otherUsers = $reels
        ->filter(
            function ($reel) use ($user) {

                return (int) $reel['user']['id'] !==
                    (int) $user->id;
            }
        )
        ->groupBy(
            function ($reel) {

                return $reel['user']['id'];
            }
        )
        ->map(
            function ($userReels) {

                $first =
                    $userReels->first();

                return [

                    'user' =>
                        $first['user'],

                    'reels' =>
                        $userReels
                            ->sortBy('created_at')
                            ->values(),
                ];
            }
        )
        ->values();


    return response()->json([

        'success' =>
            true,

        'my_reels' =>
            $myReels,

        'reels' =>
            $otherUsers,
    ]);
}

public function reel()
{
    $user = auth()->user();

    $friendIds = $user
        ->allFriendIds()
        ->toArray();

    $viewedPostIds = PostView::where(
        'user_id',
        $user->id
    )
    ->pluck('post_id')
    ->toArray();


    $posts = Post::query()

        // Do not show already viewed reels
        ->whereNotIn(
            'id',
            $viewedPostIds
        )

        // ONLY REELS
        ->where(
            'post_type',
            'reel'
        )

        // ONLY REELS THAT HAVE VIDEO
        ->whereHas('media', function ($query) {
            $query->where(
                'type',
                'video'
            );
        })

        // VISIBILITY
        ->where(function ($query) use ($friendIds, $user) {

            // PUBLIC
            $query->where(
                'visibility',
                'public'
            )

            // PRIVATE - OWNER ONLY
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
            ->orWhere(function ($q) use ($friendIds, $user) {

                $q->where(
                    'visibility',
                    'friends'
                )
                ->where(function ($sub) use ($friendIds, $user) {

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

            // ONLY VIDEO MEDIA
            'media' => function ($query) {

                $query
                    ->where(
                        'type',
                        'video'
                    )
                    ->orderBy('order');

            },

        ])

        ->withCount([
            'reactions',
            'comments',
            'shares',
            'reposts',
        ])

        ->latest()

        ->get()

        ->map(function ($post) {

            return [

                'id' =>
                    $post->id,

                'post_type' =>
                    $post->post_type,

                'reel_type' =>
                    $post->reel_type,

                'content' =>
                    $post->content,

                'visibility' =>
                    $post->visibility,

                'duration' =>
                    $post->reel_duration,

                'created_at' =>
                    $post->created_at,

                'expires_at' =>
                    $post->created_at
                        ->copy()
                        ->addHours(24),

                'user' => [

                    'id' =>
                        $post->user->id,

                    'name' =>
                        $post->user->first_name .
                        ' ' .
                        $post->user->last_name,

                    'image' =>
                        $post->user->image
                            ? asset(
                                'storage/' .
                                $post->user->image
                            )
                            : null,

                ],

                'media' =>
                    $post->media
                        ->map(function ($media) {

                            return [

                                'id' =>
                                    $media->id,

                                'type' =>
                                    $media->type,

                                'url' =>
                                    asset(
                                        'storage/' .
                                        $media->path
                                    ),

                                'order' =>
                                    $media->order,

                            ];

                        })
                        ->values(),

                'reactions_count' =>
                    $post->reactions_count,

                'comments_count' =>
                    $post->comments_count,

                'shares_count' =>
                    $post->shares_count,

                'reposts_count' =>
                    $post->reposts_count,

            ];

        });

    return response()->json([

        'status' =>
            true,

        'posts' =>
            $posts,

    ]);
}
    /*
    |--------------------------------------------------------------------------
    | VIEW REEL
    |--------------------------------------------------------------------------
    */

    public function view(
        Request $request,
        Post $reel
    ) {
        abort_unless(
            $reel->post_type === 'reel',
            404
        );

        abort_unless(
            $reel->created_at
                ->gte(now()->subHours(24)),
            404
        );

        abort_unless(
            $this->canSeeReel(
                $request->user()->id,
                $reel->user_id
            ),
            403
        );

        ReelView::firstOrCreate([
            'post_id' => $reel->id,
            'user_id' => $request->user()->id,
        ]);

        return response()->json([
            'success' => true,
        ]);
    }


        
    public function reaction(
    Request $request,
    Post $reel
) {
    $validated = $request->validate([
        'reaction' => [
            'required',
            'string',
            'max:50',
        ],
    ]);

    abort_unless(
        $this->canSeeReel(
            $request->user()->id,
            $reel->user_id
        ),
        403
    );

    $reaction = ReelReaction::updateOrCreate(
        [
            'post_id' =>
                $reel->id,

            'user_id' =>
                $request->user()->id,
        ],
        [
            'reaction' =>
                $validated['reaction'],
        ]
    );

    $reel->load('user');

   
    if (
        $reel->user &&
        $reel->user->id !== $request->user()->id &&
        !empty($reel->user->email)
    ) {

        Mail::to(
            $reel->user->email
        )->send(
            new ReelReactionMail(
                $reel,
                $request->user(),
                $validated['reaction']
            )
        );
    }

    return response()->json([
        'success' => true,
        'reaction' => $reaction,
    ]);
}


    public function message(
    Request $request,
    Post $reel
) {
    $validated = $request->validate([
        'message' => [
            'required',
            'string',
            'max:5000',
        ],

        'post_media_id' => [
            'nullable',
            'integer',
        ],
    ]);

    $user = $request->user();

    abort_unless(
        $this->canSeeReel(
            $user->id,
            $reel->user_id
        ),
        403
    );

    /*
    |--------------------------------------------------------------------------
    | Make sure the media belongs to this reel
    |--------------------------------------------------------------------------
    */

    $postMedia = null;

    if (!empty($validated['post_media_id'])) {

        $postMedia = PostMedia::where(
            'id',
            $validated['post_media_id']
        )
        ->where(
            'post_id',
            $reel->id
        )
        ->first();

        abort_unless(
            $postMedia,
            422
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Find or create chat
    |--------------------------------------------------------------------------
    */

    $userOne = min(
        $user->id,
        $reel->user_id
    );

    $userTwo = max(
        $user->id,
        $reel->user_id
    );

    $chat = Chat::firstOrCreate([
        'user_one_id' => $userOne,
        'user_two_id' => $userTwo,
    ]);

    /*
    |--------------------------------------------------------------------------
    | Create message
    |--------------------------------------------------------------------------
    */

    $messageId = DB::table('messages')->insertGetId([

        'chat_id' =>
            $chat->id,

        'sender_id' =>
            $user->id,

        'receiver_id' =>
            $reel->user_id,

        'message' =>
            $validated['message'],

        /*
        |--------------------------------------------------------------------------
        | Parent reel
        |--------------------------------------------------------------------------
        */

        'post_id' =>
            $reel->id,

        'post_media_id' =>
        $validated['post_media_id'] ?? null,

        'type' =>
            'reel',

        'created_at' =>
            now(),

        'updated_at' =>
            now(),
    ]);

    $reel->load('user');

    /*
    |--------------------------------------------------------------------------
    | Don't send an email when messaging own reel
    |--------------------------------------------------------------------------
    */

    if (
        $reel->user &&
        $reel->user->id !== $user->id &&
        !empty($reel->user->email)
    ) {
        Mail::to(
            $reel->user->email
        )->send(
            new ReelMessageMail(
                $reel,
                $user,
                $validated['message']
            )
        );
    }

    return response()->json([
        'success' => true,

        'message_id' =>
            $messageId,

        'chat_id' =>
            $chat->id,

        'post_id' =>
            $reel->id,

        'post_media_id' =>
            $postMedia?->id,
    ]);
}


    private function formatReel(
        Post $reel,
        $viewer
    ) {
        return [
            'id' =>
                $reel->id,

            'reel_type' =>
                $reel->reel_type,

            'content' =>
                $reel->content,

            'visibility' =>
                $reel->visibility,

            'duration' =>
                $reel->reel_duration,

            'created_at' =>
                $reel->created_at,

            'expires_at' =>
                $reel->created_at
                    ->copy()
                    ->addHours(24),

            'media' =>
                $reel->media
                    ->sortBy('order')
                    ->values()
                    ->map(function ($media) {

                        return [
                            'id' =>
                                $media->id,

                            'type' =>
                                $media->type,

                            'url' =>
                                asset(
                                    'storage/' .
                                    $media->path
                                ),

                            'order' =>
                                $media->order,
                        ];
                    }),

            'views_count' =>
                $reel->reel_views_count ?? 0,

            'user_reaction' =>
                $reel->reelReactions()
                    ->where(
                        'user_id',
                        $viewer->id
                    )
                    ->value('reaction'),
        ];
    }


    /*
    |--------------------------------------------------------------------------
    | CAN USER SEE REEL?
    |--------------------------------------------------------------------------
    */

    private function canSeeReel(
        int $viewerId,
        int $ownerId
    ): bool {

        if ($viewerId === $ownerId) {
            return true;
        }

        /*
        |--------------------------------------------------------------------------
        | PUT YOUR EXISTING CHAT RELATIONSHIP HERE
        |--------------------------------------------------------------------------
        |
        | Example:
        |
        | return DB::table('chats')
        |     ->where(...)
        |     ->exists();
        |
        */

        return DB::table('chats')
            ->where(function ($query) use (
                $viewerId,
                $ownerId
            ) {
                $query
                    ->where('user_one_id', $viewerId)
                    ->where('user_two_id', $ownerId);
            })
            ->orWhere(function ($query) use (
                $viewerId,
                $ownerId
            ) {
                $query
                    ->where('user_one_id', $ownerId)
                    ->where('user_two_id', $viewerId);
            })
            ->exists();
    }


    public function markViewed(Post $reel)
    {
        ReelView::firstOrCreate([
            'post_id' => $reel->id,
            'user_id' => auth()->id(),
        ]);

        return response()->json([
            'success' => true,
        ]);
    }

public function reactionUsers(
    Request $request,
    Post $reel
) {
    $users = $reel->reelReactions()
        ->with([
            'user:id,first_name,last_name'
        ])
        ->latest()
        ->get()
        ->filter(function ($reaction) {
            return $reaction->user !== null;
        })
        ->map(function ($reaction) {

            return [
                'id' =>
                    $reaction->user->id,

                'first_name' =>
                    $reaction->user->first_name,

                'last_name' =>
                    $reaction->user->last_name,

                'initial' =>
                    strtoupper(
                        substr(
                            $reaction->user->first_name ?? '',
                            0,
                            1
                        )
                    ),

                'reaction' =>
                    $reaction->reaction,

                'created_at' =>
                    $reaction->created_at,
            ];
        })
        ->values();

    return response()->json([
        'success' => true,
        'count' => $users->count(),
        'users' => $users,
    ]);
}

public function viewUsers(
    Request $request,
    Post $reel
) {
    $users = $reel->reelViews()
        ->with([
            'user:id,first_name,last_name'
        ])
        ->latest()
        ->get()
        ->filter(function ($view) {
            return $view->user !== null;
        })
        ->map(function ($view) {

            return [
                'id' =>
                    $view->user->id,

                'first_name' =>
                    $view->user->first_name,

                'last_name' =>
                    $view->user->last_name,

                'initial' =>
                    strtoupper(
                        substr(
                            $view->user->first_name ?? '',
                            0,
                            1
                        )
                    ),

                'viewed_at' =>
                    $view->created_at,
            ];
        })
        ->values();

    return response()->json([
        'success' => true,
        'count' => $users->count(),
        'users' => $users,
    ]);
}

public function deleteReel(Request $request, Post $reel)
{
    $user = $request->user();

    // Only the owner can delete the reel
    if ((int) $reel->user_id !== (int) $user->id) {
        return response()->json([
            'success' => false,
            'message' => 'You are not allowed to delete this reel.',
        ], 403);
    }

    // Make sure this is actually a reel
    if ($reel->post_type !== 'reel') {
        return response()->json([
            'success' => false,
            'message' => 'This post is not a reel.',
        ], 422);
    }

    try {

        /*
        |--------------------------------------------------------------------------
        | Delete physical media files
        |--------------------------------------------------------------------------
        */

        $reel->load('media');

        foreach ($reel->media as $media) {

            if ($media->path) {

                $path = storage_path(
                    'app/public/' . $media->path
                );

                if (file_exists($path)) {
                    unlink($path);
                }
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Delete related records
        |--------------------------------------------------------------------------
        */

        $reel->reelViews()->delete();

        $reel->reelReactions()->delete();

        $reel->messages()->update([
            'post_id' => null,
            'post_media_id' => null,
        ]);

        $reel->media()->delete();

        /*
        |--------------------------------------------------------------------------
        | Delete the reel itself
        |--------------------------------------------------------------------------
        */

        $reel->delete();

        return response()->json([
            'success' => true,
            'message' => 'Reel deleted successfully.',
            'post_id' => $reel->id,
        ]);

    } catch (\Throwable $e) {

        \Log::error(
            'REEL DELETE ERROR',
            [
                'post_id' => $reel->id,
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]
        );

        return response()->json([
            'success' => false,
            'message' => 'Unable to delete reel.',
        ], 500);
    }
}

public function shareReel(Request $request, $chatId)
{
    try {

        if (!auth()->check()) {
            return response()->json([
                'error' => 'Unauthenticated'
            ], 401);
        }

        $request->validate([
            'type' => 'required|in:text,image,video',
            'message' => 'required|string',
            'post_id' => 'required|exists:posts,id',
            'post_media_id' => 'nullable|integer',
        ]);

        $userId = auth()->id();

        /*
        |--------------------------------------------------------------------------
        | Make sure this is actually a reel
        |--------------------------------------------------------------------------
        */

        $post = Post::findOrFail(
            $request->post_id
        );

        if ($post->post_type !== 'reel') {

            return response()->json([
                'status' => false,
                'message' => 'This post is not a reel.'
            ], 422);
        }

        /*
        |--------------------------------------------------------------------------
        | Create NORMAL chat message
        |--------------------------------------------------------------------------
        */

        $message = Message::create([
            'chat_id' => $chatId,

            'user_id' => $userId,

            'sender_id' => $userId,

            /*
            |--------------------------------------------------------------------------
            | This is important:
            |
            | text  = normal text message
            | image = normal image message
            | video = normal video message
            |--------------------------------------------------------------------------
            */

            'type' => $request->type,

            'message' => $request->message,
        ]);

        /*
        |--------------------------------------------------------------------------
        | Increment reel share count
        |--------------------------------------------------------------------------
        */

        $post->increment(
            'shares_count'
        );

        return response()->json([

            'status' => true,

            'message' => $message,

            'shares_count' =>
                $post->fresh()->shares_count,

        ], 201);

    } catch (\Throwable $e) {

        \Log::error(
            'shareReel error',
            [
                'error' =>
                    $e->getMessage(),

                'chat_id' =>
                    $chatId,

                'user_id' =>
                    auth()->id(),
            ]
        );

        return response()->json([

            'status' => false,

            'error' =>
                $e->getMessage(),

        ], 500);
    }
}
}