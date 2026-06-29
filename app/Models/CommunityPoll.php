<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CommunityPoll extends Model
{
    protected $fillable = [

        'community_id',
        'sender_id',
        'question',
        'multiple_choice',
        'expires_at'

    ];

    protected $casts = [

        'multiple_choice' => 'boolean',
        'expires_at' => 'datetime'

    ];

    public function community()
    {
        return $this->belongsTo(Community::class);
    }

    public function sender()
    {
        return $this->belongsTo(
            User::class,
            'sender_id'
        );
    }

    public function options()
    {
        return $this->hasMany(
            CommunityPollOption::class,
            'poll_id'
        );
    }

    public function message()
    {
        return $this->hasOne(
            CommunityMessage::class,
            'poll_id'
        );
    }

    public function votes()
    {
        return $this->hasMany(
            CommunityPollVote::class,
            'poll_id'
        );
    }
}