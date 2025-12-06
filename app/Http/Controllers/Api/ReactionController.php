<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ReactionController extends Controller {
    public function toggle(Request $request) {
        $data = $request->validate([
            'type'=>'required|string', // "video" or "comment"
            'id'=>'required|integer',
            'emoji'=>'required|string'
        ]);
        $model = null;
        if ($data['type'] === 'video') $model = \App\Models\Video::findOrFail($data['id']);
        if ($data['type'] === 'comment') $model = \App\Models\Comment::findOrFail($data['id']);
        if (! $model) return response()->json(['message'=>'Invalid type'], 422);

        $existing = $model->reactions()->where('user_id', $request->user()->id)->where('emoji', $data['emoji'])->first();
        if ($existing) {
            $existing->delete();
            return response()->json(['removed'=>true]);
        }
        $model->reactions()->create(['user_id'=>$request->user()->id,'emoji'=>$data['emoji']]);
        return response()->json(['added'=>true]);
    }
}
