<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Comment;
use App\Models\Video;

class CommentController extends Controller
{
    public function store(Request $request, Video $video) {
        $data = $request->validate(['body'=>'required|string','parent_id'=>'nullable|exists:comments,id']);
        $comment = Comment::create([
            'video_id'=>$video->id,
            'user_id'=>$request->user()->id,
            'parent_id'=>$data['parent_id'] ?? null,
            'body'=>$data['body']
        ]);
        $comment->load('user');
        return response()->json(['status'=>true,'comment'=>$comment],201);
    }

    public function update(Request $request, Comment $comment) {
        $this->authorize('update', $comment);
        $data = $request->validate(['body'=>'required|string']);
        $comment->update(['body'=>$data['body']]);
        return response()->json(['status'=>true,'comment'=>$comment]);
    }

    public function destroy(Request $request, Comment $comment) {
        $this->authorize('delete', $comment);
        $comment->delete();
        return response()->json(['status'=>true]);
    }
}
