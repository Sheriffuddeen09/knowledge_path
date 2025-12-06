<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Video;

class ShareController extends Controller {
    public function share(Request $request, Video $video) {
        $data = $request->validate(['to'=>'nullable|string']); // 'to' could be email/username
        // Optionally log share
        // Share::create([...])
        return response()->json(['status'=>true,'url'=>route('videos.show', $video->id)]);
    }
}
