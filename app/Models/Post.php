<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Post extends Model
{
    protected $fillable = [
        'user_id',
        'post_type',
        'reel_type',
        'content',
        'image',
        'video',
        'views',
        'visibility',
        'original_post_id',
        'is_new_home',
        'is_new_video',
        'post_media', 
        'trim_start',
        'trim_end',
        'background_color',
        'font',
        'reel_duration',
    ];

    protected $casts = [
    'trim_start' => 'float',
    'trim_end' => 'float',
    'reel_duration' => 'integer',
        ];
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function reactions()
    {
        return $this->hasMany(PostReaction::class);
    }

    public function rootOriginal()
    {
        $post = $this;

        while ($post->originalPost) {
            $post = $post->originalPost;
        }

        return $post;
    }

    public function comments()
    {
        return $this->hasMany(PostComment::class);
    }

    public function media()
    {
        return $this->hasMany(PostMedia::class);
    }

    public function postMedia()
    {
        return $this->hasMany(PostMedia::class);
    }

    public function hiddenBy()
    {
        return $this->hasMany(HiddenPost::class);
    }

    public function saves()
    {
        return $this->belongsToMany(User::class, 'post_saves')
                    ->withTimestamps();
    }
    public function shares()
    {
        return $this->hasMany(PostShare::class);
    }

    public function originalPost()
    {
        return $this->belongsTo(Post::class, 'original_post_id');
    }

    public function reposts()
    {
        return $this->hasMany(Post::class, 'original_post_id');
    }

    public function views()
    {
        return $this->hasMany(PostView::class);
    }

    public function friends()
    {
        return $this->belongsToMany(User::class, 'friends')
            ->wherePivot('status', 'accepted');
    }

    public function reelViews(): HasMany
    {
        return $this->hasMany(ReelView::class);
    }

    public function reelReactions(): HasMany
    {
        return $this->hasMany(ReelReaction::class);
    }

    public function messages()
    {
        return $this->hasMany(Message::class);
    }

}
