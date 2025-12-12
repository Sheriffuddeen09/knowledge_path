<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReplyReaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'reply_id',
        'user_id',
        'emoji',
    ];

    // Optional: define relations
    public function user() {
        return $this->belongsTo(User::class);
    }

    public function reply() {
        return $this->belongsTo(Comment::class, 'reply_id');
    }
}
