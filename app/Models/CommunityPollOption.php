<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CommunityPollOption extends Model
{
    protected $fillable = [

        'poll_id',
        'option',
        'votes'

    ];

    public function poll()
    {
        return $this->belongsTo(
            CommunityPoll::class,
            'poll_id'
        );
    }

    public function voteUsers()
    {
        return $this->hasMany(
            CommunityPollVote::class,
            'option_id'
        );
    }
}