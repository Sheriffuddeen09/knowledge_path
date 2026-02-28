<?php
namespace App\Http\Controllers;

use App\Events\PostReactionUpdated;
use App\Models\PostReaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\Notification;
use App\Models\Post;


class PostReactionController extends Controller
{
   
    public function index($post_id)
{
    $users = PostReaction::with('user')
        ->where('post_id', $post_id)
        ->orderBy('created_at','desc')
        ->get()
        ->map(function ($r) {
            return [
                'id' => $r->user->id,
                'name' => trim(
                    ($r->user->first_name ?? '') . ' ' .
                    ($r->user->last_name ?? '')
                ),
                'emoji' => $r->emoji,
            ];
        });

    $counts = PostReaction::where('post_id', $post_id)
        ->select('emoji', DB::raw('count(*) as total'))
        ->groupBy('emoji')
        ->get()
        ->pluck('total','emoji');

    $myReaction = null;

    if (Auth::check()) {
        $myReaction = PostReaction::where('post_id', $post_id)
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
    $request->validate([
        'emoji' => 'required|string|max:10'
    ]);

    $user = Auth::user();
    if (!$user) {
        return response()->json(['error' => 'Unauthenticated'], 401);
    }

    // Save or update reaction
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

    $post = Post::find($post_id);

    if ($post && $post->user_id !== $user->id) {

    $reactors = PostReaction::where('post_id', $post_id)
        ->with('user')
        ->get();

    // Map to full names
    $reactorNames = $reactors->map(function ($r) {
        $user = $r->user;
        return trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? ''));
    })->toArray();

    Notification::updateOrCreate(
        [
            'user_id' => $post->user_id,
            'type' => 'post_reaction',
        ],
        [
            'data' => json_encode([
                'post_id' => $post->id,
                'reactors' => $reactorNames, // now full names
                'count' => count($reactorNames),
            ]),
            'redirect_url' => "/post/{$post->id}", // 👈 Redirect to post
            'read' => false
        ]
    );
}

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
