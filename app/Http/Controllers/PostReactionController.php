<?php
namespace App\Http\Controllers;

use App\Events\PostReactionUpdated;
use App\Models\PostReaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PostReactionController extends Controller
{
    // GET reactions for a Post (counts + users + my_reaction)
    public function index($post_id)
    {
        $users = PostReaction::with('user')
            ->where('post_id', $post_id)
            ->orderBy('created_at','desc')
            ->get()
            ->map(fn($r) => [
                'id' => $r->user->id,
                'name' => ($r->user->first_name ?? $r->user->name ?? '') . ' ' . ($r->user->last_name ?? ''),
                'emoji' => $r->emoji,
            ]);

        $counts = PostReaction::where('post_id', $post_id)
            ->select('emoji', DB::raw('count(*) as total'))
            ->groupBy('emoji')
            ->get()
            ->pluck('total','emoji');

        $myReaction = null;
        if (Auth::check()) {
            $myReaction = PostReaction::where('post_id',$post_id)
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
    public function store(Request $request, $post_id)
{
    $request->validate(['emoji'=>'required|string|max:10']);

    $user = Auth::user();
    if (!$user) return response()->json(['error'=>'Unauthenticated'],401);

    PostReaction::updateOrCreate(
        [
            'post_id' => $post_id,
            'user_id' => $user->id,
        ],
        [
            'emoji' => $request->emoji,
            'type'  => 'post',
        ]
    );

    return $this->broadcastAndReturn($post_id);
}

    // DELETE reaction (unlike)
    public function destroy($post_id)
    {
        $user = Auth::user();
        if (!$user) return response()->json(['error'=>'Unauthenticated'],401);

        PostReaction::where('post_id',$post_id)->where('user_id',$user->id)->delete();

        return $this->broadcastAndReturn($post_id);
    }

    // helper: compute counts and users, broadcast event and return data
    protected function broadcastAndReturn($post_id)
    {
        $users = PostReaction::with('user')->where('post_id',$post_id)->get()
            ->map(fn($r) => [
                'id' => $r->user->id,
                'name' => ($r->user->first_name ?? $r->user->name ?? '') . ' ' . ($r->user->last_name ?? ''),
                'emoji' => $r->emoji,
            ]);

        $counts = PostReaction::where('post_id',$post_id)
            ->select('emoji', DB::raw('count(*) as total'))
            ->groupBy('emoji')
            ->get()
            ->pluck('total','emoji');

        event(new PostReactionUpdated($post_id, $counts, $users));

        return response()->json([
            'counts' => $counts,
            'users' => $users,
            'my_reaction' => PostReaction::where('post_id',$post_id)->where('user_id', auth()->id())->value('emoji')
        ]);
    }
}
