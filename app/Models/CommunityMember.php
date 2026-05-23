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
    ];
}