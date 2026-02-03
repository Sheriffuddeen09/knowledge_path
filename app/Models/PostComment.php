<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PostComment extends Model
{
    protected $fillable = [
        'post_id',
        'user_id',
        'parent_id',
        'body',
        'image',
    ];

    /* ---------------- RELATIONSHIPS ---------------- */

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function post()
    {
        return $this->belongsTo(Post::class);
    }

    // Replies (recursive)
    public function replies()
    {
        return $this->hasMany(PostComment::class, 'parent_id')
                    ->with('replies.user');
    }

    public function comment()
    {
        return $this->belongsTo(PostComment::class, 'comment_id');
    }

    public function reactions()
            {
                return $this->hasMany(PostCommentReaction::class, 'comment_id'); 
            }

}

