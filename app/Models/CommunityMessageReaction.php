<?php

// app/Models/CommunityMessageReaction.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CommunityMessageReaction extends Model
{
    protected $fillable = [
        'community_message_id',
        'user_id',
        'emoji',
    ];

    public function message()
    {
        return $this->belongsTo(
            CommunityMessage::class,
            'community_message_id'
        );
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}