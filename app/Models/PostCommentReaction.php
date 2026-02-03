<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PostCommentReaction extends Model
{
    protected $fillable = ['comment_id', 'user_id', 'emoji'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function comment()
    {
        return $this->belongsTo(PostComment::class, 'comment_id');
    }
}

