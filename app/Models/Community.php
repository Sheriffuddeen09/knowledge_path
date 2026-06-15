<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Community extends Model
{
   
    protected $fillable = [
        'creator_id',
        'community_name',
        'community_description',
        'community_image',
        'owner_id',
        'only_admin_can_message',
        'disappearing_mode',
        'is_deleted',   // ✅ THIS GOES HERE
        'deleted_by',
        'deleted_at',
        'invite_token',
    ];

    protected $casts = [
        'is_deleted' => 'boolean',
        'deleted_at' => 'datetime',
    ];

    public function members()
    {
        return $this->belongsToMany(
            User::class,
            'community_members'
        )
        ->withPivot([
            'role',
            'can_message',
            'muted',
            'joined_at',
            'membership_status',
            'last_read_message_id',
        ])
        ->withTimestamps();
    }

        public function lastMessage()
            {
                return $this->hasOne(
                    CommunityMessage::class
                )->latestOfMany();
            }

    // ✅ OWNER
    public function owner()
    {
        return $this->belongsTo(
            User::class,
            'owner_id'
        );
    }

    // ✅ CREATOR
    public function creator()
    {
        return $this->belongsTo(
            User::class,
            'creator_id'
        );
    }
    public function messages()
    {
        return $this->hasMany(
            CommunityMessage::class
        );
    }
}