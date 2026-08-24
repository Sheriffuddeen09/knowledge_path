<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Models\ReelView;
use App\Models\ReelReaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;
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
            'required',
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
            $request->visibility,

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
        ->where('created_at', '>=', now()->subHours(24))
        ->whereIn('visibility', [
            'public',
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

        ->withCount([
            'reelViews',
            'reactions',
        ])

        ->withExists([
            'reelViews as has_viewed' => function ($query) use ($user) {
                $query->where(
                    'user_id',
                    $user->id
                );
            },
        ])

        ->latest()
        ->get();

    $reels = $reels->filter(
        function ($reel) use ($user) {

            // Own reels
            if (
                (int) $reel->user_id ===
                (int) $user->id
            ) {
                return true;
            }

            // Public reels
            if (
                $reel->visibility === 'public'
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

    /*
    |--------------------------------------------------------------------------
    | FORMAT REELS
    |--------------------------------------------------------------------------
    */

    $reels = $reels->map(
        function ($reel) {

            return [
                'id' => $reel->id,

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
                    $reel->reel_views_count,

                'user_reaction' =>
                    $reel->reactions
                        ->where(
                            'user_id',
                            auth()->id()
                        )
                        ->first()?->reaction,

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

                /*
                |--------------------------------------------------------------------------
                | MEDIA
                |--------------------------------------------------------------------------
                */

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

                                    'description' => $media->description
                                            ? [
                                                'id' => $media->description->id,
                                                'type' => $media->description->type,
                                                'content' => $media->description->content,
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
    | GROUP BY USER
    |--------------------------------------------------------------------------
    */

    $grouped = $reels
        ->groupBy('user_id');


    $myReels = $reels
        ->filter(
            function ($reel) use ($user) {

                return (int) $reel['user']['id'] ===
                    (int) $user->id;
            }
        )
        ->sortBy('created_at')
        ->values();

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
                    'user' => $first['user'],

                    'reels' =>
                        $userReels
                            ->sortBy('created_at')
                            ->values(),
                ];
            }
        )
        ->values();

    return response()->json([
        'success' => true,

        'my_reels' =>
            $myReels,

        'reels' =>
            $otherUsers,
    ]);
}

    public function show(
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

        $reel->load([
            'user:id,first_name,last_name',
            'media',
        ]);

        return response()->json([
            'success' => true,
            'reel' =>
                $this->formatReel(
                    $reel,
                    $request->user()
                ),
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


    /*
    |--------------------------------------------------------------------------
    | REACTION
    |--------------------------------------------------------------------------
    */

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

        $reaction =
            ReelReaction::updateOrCreate(
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

        return response()->json([
            'success' => true,
            'reaction' => $reaction,
        ]);
    }


    /*
    |--------------------------------------------------------------------------
    | SEND MESSAGE WITH REEL
    |--------------------------------------------------------------------------
    */

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
        ]);

        abort_unless(
            $this->canSeeReel(
                $request->user()->id,
                $reel->user_id
            ),
            403
        );

        /*
        |--------------------------------------------------------------------------
        | IMPORTANT
        |
        | Replace this section with your existing
        | chat/message model if you already have one.
        |--------------------------------------------------------------------------
        */

        $message = DB::table('messages')->insertGetId([
            'sender_id' =>
                $request->user()->id,

            'receiver_id' =>
                $reel->user_id,

            'message' =>
                $validated['message'],

            'post_id' =>
                $reel->id,

            'type' =>
                'reel',

            'created_at' =>
                now(),

            'updated_at' =>
                now(),
        ]);

        return response()->json([
            'success' => true,
            'message_id' => $message,
        ]);
    }


    /*
    |--------------------------------------------------------------------------
    | FORMAT REEL
    |--------------------------------------------------------------------------
    */

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


}