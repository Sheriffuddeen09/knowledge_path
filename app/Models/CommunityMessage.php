<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CommunityMessage extends Model
{
    protected $fillable = [
        'community_id',
        'sender_id',
        'message',
        'type',
        'file',
        'replied_to',
    ];

    public function sender()
    {
        return $this->belongsTo(User::class);
    }

    public function community()
    {
        return $this->belongsTo(
            Community::class
        );
    }
}