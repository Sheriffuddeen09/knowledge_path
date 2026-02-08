<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Post;

class PostStreamController extends Controller

{
    public function stream(Request $request, $postId)
    {
        $post = Post::with('media')->findOrFail($postId);

        $media = $post->media->firstWhere('type', 'video');
        if (!$media) {
            abort(404, 'No video found for this post');
        }

        $path = storage_path('app/public/' . $media->path);

        if (!file_exists($path)) {
            abort(404, 'Video file not found');
        }

        $size = filesize($path);
        $start = 0;
        $end = $size - 1;

        if ($request->headers->has('Range')) {
            [$unit, $range] = explode('=', $request->header('Range'));
            [$rangeStart, $rangeEnd] = explode('-', $range);

            $start = intval($rangeStart);
            $end = $rangeEnd === '' ? $end : intval($rangeEnd);
        }

        $length = $end - $start + 1;

        $headers = [
            'Content-Type' => 'video/mp4',
            'Content-Length' => $length,
            'Accept-Ranges' => 'bytes',
            'Content-Range' => "bytes $start-$end/$size",
        ];

        return response()->stream(function () use ($path, $start, $length) {
            $file = fopen($path, 'rb');
            fseek($file, $start);

            $buffer = 8192;
            while (!feof($file) && $length > 0) {
                $read = min($buffer, $length);
                echo fread($file, $read);
                flush();
                $length -= $read;
            }

            fclose($file);
        }, 206, $headers);
    }
}
