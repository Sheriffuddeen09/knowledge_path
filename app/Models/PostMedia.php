<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PostMedia extends Model
{
    protected $fillable = ['post_id', 'type', 'path', 'order','description',];

    public function post()
    {
        return $this->belongsTo(Post::class);
    }

    public function description()
            {
                return $this->hasOne(
                    ReelMediaDescription::class,
                    'post_media_id'
                );
            }
}
