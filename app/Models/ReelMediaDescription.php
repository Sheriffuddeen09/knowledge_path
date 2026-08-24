<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReelMediaDescription extends Model
{
    protected $fillable = [
        'post_media_id',
        'type',
        'content',
    ];

    public function media()
    {
        return $this->belongsTo(
            PostMedia::class,
            'post_media_id'
        );
    }
}