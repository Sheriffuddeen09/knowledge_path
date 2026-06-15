<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

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
}