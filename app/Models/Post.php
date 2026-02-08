<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Post extends Model
{
    protected $fillable = [
        'user_id',
        'content',
        'image',
        'video',
        'views'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function reactions()
    {
        return $this->hasMany(PostReaction::class);
    }

    public function comments()
    {
        return $this->hasMany(PostComment::class);
    }

    public function media()
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
}
