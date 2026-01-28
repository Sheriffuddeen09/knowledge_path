<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PostComment extends Model
{
    protected $fillable = ['post_id','user_id','content','parent_id'];

    public function replies() {
        return $this->hasMany(PostComment::class, 'parent_id');
    }

    public function reactions() {
        return $this->morphMany(PostReaction::class, 'reactable');
    }
}
