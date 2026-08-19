<?php

namespace App\Http\Controllers;

use App\Models\Post;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;

class ReelController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'reel_type' => [
                'required',
                'in:video,image,text',
            ],

            'content' => [
                'nullable',
                'string',
                'max:5000',
            ],

            'visibility' => [
                'required',
                'in:public,friends,private',
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

            'image' => [
                'nullable',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:5120',
            ],

            'video' => [
                'nullable',
                'file',
                'mimes:mp4,mov',
                'max:51200',
            ],

            'trim_start' => [
                'nullable',
                'numeric',
                'min:0',
            ],

            'trim_end' => [
                'nullable',
                'numeric',
                'gt:trim_start',
            ],
        ]);

        $type = $request->reel_type;

        /*
        |--------------------------------------------------------------------------
        | TEXT REEL
        |--------------------------------------------------------------------------
        */

        if ($type === 'text') {

            if (!$request->filled('content')) {
                return response()->json([
                    'message' => 'Text is required for a text reel.'
                ], 422);
            }

            if (
                $request->hasFile('image') ||
                $request->hasFile('video')
            ) {
                return response()->json([
                    'message' =>
                        'Text reels cannot contain an image or video.'
                ], 422);
            }
        }

        /*
        |--------------------------------------------------------------------------
        | IMAGE REEL
        |--------------------------------------------------------------------------
        */

        if ($type === 'image') {

            if (!$request->hasFile('image')) {
                return response()->json([
                    'message' =>
                        'An image is required for an image reel.'
                ], 422);
            }

            if ($request->hasFile('video')) {
                return response()->json([
                    'message' =>
                        'An image reel cannot contain a video.'
                ], 422);
            }
        }

        /*
        |--------------------------------------------------------------------------
        | VIDEO REEL
        |--------------------------------------------------------------------------
        */

        if ($type === 'video') {

            if (!$request->hasFile('video')) {
                return response()->json([
                    'message' =>
                        'A video is required for a video reel.'
                ], 422);
            }

            if ($request->hasFile('image')) {
                return response()->json([
                    'message' =>
                        'A video reel cannot contain an image.'
                ], 422);
            }

            if (
                !$request->filled('trim_start') ||
                !$request->filled('trim_end')
            ) {
                return response()->json([
                    'message' =>
                        'Video reels require a start and end time.'
                ], 422);
            }

            $trimStart = (float) $request->trim_start;
            $trimEnd = (float) $request->trim_end;

            $duration = $trimEnd - $trimStart;

            /*
            |--------------------------------------------------------------------------
            | HARD 90 SECOND LIMIT
            |--------------------------------------------------------------------------
            */

            if ($duration <= 0) {
                return response()->json([
                    'message' =>
                        'Invalid video duration.'
                ], 422);
            }

            if ($duration > 90) {
                return response()->json([
                    'message' =>
                        'A reel video cannot be longer than 1 minute 30 seconds.'
                ], 422);
            }
        }

        /*
        |--------------------------------------------------------------------------
        | CREATE POST
        |--------------------------------------------------------------------------
        */

        $post = Post::create([
            'user_id' => auth()->id(),

            'post_type' => 'reel',

            'reel_type' => $type,

            'content' => $request->content,

            'visibility' => $request->visibility,

            'background_color' =>
                $request->background_color,

            'font' =>
                $request->font,

            'reel_duration' =>
                $type === 'video'
                    ? (int) ceil($duration)
                    : 30,

            'is_new_home' => 0,

            'is_new_video' => 1,
        ]);

        /*
        |--------------------------------------------------------------------------
        | IMAGE
        |--------------------------------------------------------------------------
        */

        if ($type === 'image') {

            $path = $request
                ->file('image')
                ->store(
                    'posts/reels/images',
                    'public'
                );

            $post->media()->create([
                'type' => 'image',
                'path' => $path,
                'order' => 0,
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | VIDEO
        |--------------------------------------------------------------------------
        */

        if ($type === 'video') {

            $video = $request->file('video');

            $originalPath = $video->store(
                'posts/reels/temp',
                'public'
            );

            $inputPath = Storage::disk('public')
                ->path($originalPath);

            $outputDirectory = 'posts/reels/videos';

            Storage::disk('public')
                ->makeDirectory($outputDirectory);

            $outputFilename =
                'reel_' .
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

            $duration = $trimEnd - $trimStart;

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

            $post->media()->create([
                'type' => 'video',
                'path' => $outputRelativePath,
                'order' => 0,
            ]);
        }

        return response()->json([
            'success' => true,

            'message' =>
                'Reel created successfully.',

            'post' => $post->load([
                'user',
                'media',
            ]),
        ], 201);
    }

public function index()
{
    $userId = auth()->id();

    $reels = Post::with([
        'user',
        'media',
        'comments.user',
    ])
    ->where('post_type', 'reel')
    ->where(function ($query) use ($userId) {

        $query
            ->where('user_id', $userId)

            ->orWhereHas('user', function ($userQuery) use ($userId) {

                $userQuery->whereHas(
                    'chatsAsUserOne',
                    function ($chatQuery) use ($userId) {
                        $chatQuery->where(
                            'user_two_id',
                            $userId
                        );
                    }
                );

            });
    })
    ->latest()
    ->get();

    return response()->json([
        'reels' => $reels,
    ]);
}

}