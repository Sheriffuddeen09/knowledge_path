<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VideoReaction extends Model
{
    protected $fillable = [
        'video_id',
        'user_id',
        'emoji',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function video()
    {
        return $this->belongsTo(Video::class);
    }
    public function reactionable()
    {
        return $this->morphTo();
    }
}
