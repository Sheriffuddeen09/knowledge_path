<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\User;

class CommunityPendingResponse extends Model
{
    protected $fillable = [
        'community_id',
        'sender_id',
        'message',
        'reply_to',
    ];

    public function sender()
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function repliedMessage()
    {
        return $this->belongsTo(
            CommunityPendingResponse::class,
            'reply_to'
        );
    }
}