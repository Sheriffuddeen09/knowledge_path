<?php

namespace App\Helpers;

use Illuminate\Support\Str;

class VideoHelper
{
    public static function process($file)
    {
        $filename = Str::random(40);

        $videoPath = "chat_files/{$filename}.mp4";
        $thumbPath = "chat_files/{$filename}.jpg";

        $fullVideoPath = storage_path("app/public/{$videoPath}");
        $fullThumbPath = storage_path("app/public/{$thumbPath}");

        // 🔥 Convert video (safe for web)
        exec("ffmpeg -i {$file->getRealPath()} -vcodec libx264 -acodec aac -movflags +faststart {$fullVideoPath}");

        // 🔥 Generate thumbnail
        exec("ffmpeg -i {$fullVideoPath} -ss 00:00:01.000 -vframes 1 {$fullThumbPath}");

        return [
            'file_url' => asset("storage/{$videoPath}"),
            'thumbnail' => asset("storage/{$thumbPath}"),
            'file_name' => $file->getClientOriginalName(),
            'type' => 'video'
        ];
    }
}