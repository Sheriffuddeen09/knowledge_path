<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommunityMember extends Model
{
    protected $table = 'community_members';

    protected $fillable = [
        'community_id',
        'user_id',
        'role',
        'membership_status',
        'joined_at',
        'can_message',
        'muted',
        'last_read_message_id',
        'hidden_until',
        'status',
        'hidden_for_admin',
    ];


      public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }


    public function community(): BelongsTo
    {
        return $this->belongsTo(Community::class, 'community_id');
    }
}