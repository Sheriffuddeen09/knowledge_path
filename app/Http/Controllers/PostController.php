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

        // ✅ WORD COUNT SAFETY CHECK
        if ($request->filled('content')) {
            $text = preg_replace('/([a-z])([A-Z])/', '$1 $2', $request->content);
            $text = preg_replace('/\s+/', ' ', trim($text));

            $wordCount = str_word_count($text);

            if ($wordCount > 50) {
                return response()->json([
                    'message' => "Text is too long. Maximum allowed is 50 words."
                ], 422);
            }
        }


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
