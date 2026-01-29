<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PostReaction extends Model
{
    protected $fillable = [
        'post_id',
        'user_id',
        'emoji',
        'type',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function post()
    {
        return $this->belongsTo(Post::class);
    }
    public function media()
    {
        return $this->belongsTo(PostMedia::class);
    }
    public function reactionable()
    {
        return $this->morphTo();
    }
}
