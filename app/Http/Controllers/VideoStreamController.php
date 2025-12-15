<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Video;
use Symfony\Component\HttpFoundation\StreamedResponse;

class VideoStreamController extends Controller
{
    public function stream(Request $request, Video $video)
    {
        // ✅ Correct storage path
        $path = storage_path('app/public/' . $video->video_path);

        if (!file_exists($path)) {
            abort(404, 'Video file not found');
        }

        $size = filesize($path);
        $start = 0;
        $length = $size;
        $end = $size - 1;

        // ✅ Range support (required for HTML5 video)
        if ($request->headers->has('Range')) {
            [$unit, $range] = explode('=', $request->header('Range'));
            [$rangeStart, $rangeEnd] = explode('-', $range);

            $start = intval($rangeStart);
            $end = $rangeEnd === '' ? $size - 1 : intval($rangeEnd);
            $length = $end - $start + 1;

            return response()->stream(function () use ($path, $start, $length) {
                $file = fopen($path, 'rb');
                fseek($file, $start);

                $buffer = 8192;
                $remaining = $length;

                while ($remaining > 0 && !feof($file)) {
                    $read = min($buffer, $remaining);
                    echo fread($file, $read);
                    flush();
                    $remaining -= $read;
                }

                fclose($file);
            }, 206, [
                'Content-Type' => 'video/mp4',
                'Content-Length' => $length,
                'Content-Range' => "bytes $start-$end/$size",
                'Accept-Ranges' => 'bytes',
            ]);
        }

        // Full file
        return response()->file($path, [
            'Content-Type' => 'video/mp4',
            'Content-Length' => $size,
            'Accept-Ranges' => 'bytes',
        ]);
    }
}
