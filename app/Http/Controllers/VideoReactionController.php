<?php
namespace App\Http\Controllers;

use App\Events\ReactionUpdated;
use App\Models\VideoReaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class VideoReactionController extends Controller
{
    // GET reactions for a video (counts + users + my_reaction)
    public function index($video_id)
    {
        $users = VideoReaction::with('user')
            ->where('video_id', $video_id)
            ->orderBy('created_at','desc')
            ->get()
            ->map(fn($r) => [
                'id' => $r->user->id,
                'name' => ($r->user->first_name ?? $r->user->name ?? '') . ' ' . ($r->user->last_name ?? ''),
                'emoji' => $r->emoji,
            ]);

        $counts = VideoReaction::where('video_id', $video_id)
            ->select('emoji', DB::raw('count(*) as total'))
            ->groupBy('emoji')
            ->get()
            ->pluck('total','emoji');

        $myReaction = null;
        if (Auth::check()) {
            $myReaction = VideoReaction::where('video_id',$video_id)
                ->where('user_id', Auth::id())
                ->value('emoji');
        }

        return response()->json([
            'counts' => $counts,
            'users' => $users,
            'my_reaction' => $myReaction,
        ]);
    }

    // POST upsert reaction (create or update)
    public function store(Request $request, $video_id)
    {
        $request->validate(['emoji'=>'required|string|max:10']);
        $user = Auth::user();
        if (!$user) return response()->json(['error'=>'Unauthenticated'],401);

        VideoReaction::updateOrCreate(
            ['video_id'=>$video_id,'user_id'=>$user->id],
            ['emoji'=>$request->emoji]
        );

        return $this->broadcastAndReturn($video_id);
    }

    // DELETE reaction (unlike)
    public function destroy($video_id)
    {
        $user = Auth::user();
        if (!$user) return response()->json(['error'=>'Unauthenticated'],401);

        VideoReaction::where('video_id',$video_id)->where('user_id',$user->id)->delete();

        return $this->broadcastAndReturn($video_id);
    }

    // helper: compute counts and users, broadcast event and return data
    protected function broadcastAndReturn($video_id)
    {
        $users = VideoReaction::with('user')->where('video_id',$video_id)->get()
            ->map(fn($r) => [
                'id' => $r->user->id,
                'name' => ($r->user->first_name ?? $r->user->name ?? '') . ' ' . ($r->user->last_name ?? ''),
                'emoji' => $r->emoji,
            ]);

        $counts = VideoReaction::where('video_id',$video_id)
            ->select('emoji', DB::raw('count(*) as total'))
            ->groupBy('emoji')
            ->get()
            ->pluck('total','emoji');

        event(new ReactionUpdated($video_id, $counts, $users));

        return response()->json([
            'counts' => $counts,
            'users' => $users,
            'my_reaction' => VideoReaction::where('video_id',$video_id)->where('user_id', auth()->id())->value('emoji')
        ]);
    }
}
