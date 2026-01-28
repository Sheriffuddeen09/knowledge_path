<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Post;
use App\Models\PostView;
use App\Models\PostReaction;
use App\Models\PostComment;



class PostController extends Controller
{
    
public function store(Request $request)
{
    $request->validate([
        'content' => 'nullable|string',

        // multiple images
        'images' => 'nullable|array',
        'images.*' => 'image|mimes:jpg,jpeg,png,webp|max:5120',

        // single video
        'video' => 'nullable|file|mimes:mp4,mov|max:51200', // 50MB
    ]);

    // empty post check
    if (
        !$request->content &&
        !$request->hasFile('images') &&
        !$request->hasFile('video')
    ) {
        return response()->json(['message' => 'Post is empty'], 422);
    }

    // ❌ block image + video together
    if ($request->hasFile('images') && $request->hasFile('video')) {
        return response()->json([
            'message' => 'You can upload images OR a video, not both.'
        ], 422);
    }

    $post = Post::create([
        'user_id' => auth()->id(),
        'content' => $request->content,
    ]);

    // ✅ save images
    if ($request->hasFile('images')) {
        foreach ($request->file('images') as $index => $image) {
            $path = $image->store('posts/images', 'public');

            $post->media()->create([
                'type' => 'image',
                'path' => $path,
                'order' => $index,
            ]);
        }
    }

    // ✅ save ONE video
    if ($request->hasFile('video')) {
        $path = $request->file('video')->store('posts/videos', 'public');

        $post->media()->create([
            'type' => 'video',
            'path' => $path,
            'order' => 0,
        ]);
    }

    return response()->json([
        'post' => $post->load('media')
    ], 201);
}


    public function index()
    {
        $posts = Post::with([
                'user:id,first_name,last_name,image',
                'reactions',
                'comments'
            ])
            ->latest()
            ->get()
            ->map(function ($post) {
                return [
                    'id' => $post->id,
                    'content' => $post->content,
                    'image' => $post->image ? asset('storage/' . $post->image) : null,
                    'video' => $post->video ? asset('storage/' . $post->video) : null,
                    'views' => $post->views,
                    'created_at' => $post->created_at->diffForHumans(),

                    'user' => [
                        'id' => $post->user->id,
                        'name' => $post->user->first_name . ' ' . $post->user->last_name,
                        'avatar' => $post->user->image
                            ? asset('storage/' . $post->user->image)
                            : null
                    ],

                    'reactions_count' => $post->reactions->count(),
                    'comments_count' => $post->comments->count()
                ];
            });

        return response()->json([
            'status' => true,
            'posts' => $posts
        ]);
    }

public function show(Post $post)
{
    PostView::firstOrCreate([
        'post_id' => $post->id,
        'user_id' => auth()->id()
    ]);

    $post->increment('views');
}


public function comment(Request $request)
{
    $request->validate([
        'post_id' => 'required|exists:posts,id',
        'content' => 'required|string',
        'parent_id' => 'nullable|exists:comments,id'
    ]);

    return PostComment::create([
        'post_id' => $request->post_id,
        'user_id' => auth()->id(),
        'content' => $request->content,
        'parent_id' => $request->parent_id
    ]);
}


public function toggle(Request $request)
{
    $reaction = PostReaction::updateOrCreate(
        [
            'user_id' => auth()->id(),
            'reactable_id' => $request->id,
            'reactable_type' => $request->type,
        ],
        ['type' => $request->reaction]
    );

    return $reaction;
}

}
