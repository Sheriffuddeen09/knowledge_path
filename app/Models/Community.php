<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Community extends Model
{
   
    protected $fillable = [
        'creator_id',
        'community_name',
        'community_description',
        'owner_id',
        'only_admin_can_message',
        'disappearing_mode',
    ];

    public function members()
        {
            return $this->belongsToMany(
                User::class,
                'community_members',
                'community_id',
                'user_id'
            )->withTimestamps();
        }

    public function messages()
    {
        return $this->hasMany(
            CommunityMessage::class
        );
    }
}