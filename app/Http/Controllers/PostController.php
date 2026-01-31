<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Post;
use App\Models\PostView;
use App\Models\PostReaction;
use App\Models\PostComment;
use Illuminate\Support\Str;
use App\Models\PostMedia;




class PostController extends Controller
{
    
public function store(Request $request)
{

        $request->validate([
            'content' => 'nullable|string',
            'images' => 'nullable|array',
            'images.*' => 'image|mimes:jpg,jpeg,png,webp|max:5120',
            'video' => 'nullable|file|mimes:mp4,mov|max:51200',
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
                'comments',
                'media'
            ])
            ->latest()
            ->get()
            ->map(function ($post) {
                return [
                    'id' => $post->id,
                    'content' => $post->content,

                    'media' => $post->media->map(fn ($m) => [
                        'id' => $m->id,
                        'type' => $m->type, // image | video
                        'url' => asset('storage/' . $m->path),
                    ]),

                    'created_at' => $post->created_at->diffForHumans(),

                    'user' => [
                        'id' => $post->user->id,
                        'name' => $post->user->first_name.' '.$post->user->last_name,
                    ],

                    'reactions_count' => $post->reactions->count(),
                    'comments_count' => $post->comments->count(),
                ];
            });

        return response()->json([
            'status' => true,
            'posts' => $posts
        ]);
    }

public function show(Post $post)
{
    $post->load([
        'user:id,first_name,last_name,image',
        'media',
        'reactions',
        'comments.user',
        'comments.replies.user',
    ]);

    return response()->json([
        'post' => [
            'id' => $post->id,
            'content' => $post->content,
            'created_at' => $post->created_at->diffForHumans(),

            'user' => [
                'id' => $post->user->id,
                'name' => $post->user->first_name.' '.$post->user->last_name,
                'image' => $post->user->image,
            ],

            'media' => $post->media->map(fn ($m) => [
                'id' => $m->id,
                'type' => $m->type,
                'url' => asset('storage/'.$m->path),
            ]),

            'reactions_count' => $post->reactions->count(),
            'comments_count' => $post->comments->count(),
        ]
    ]);
}




}
