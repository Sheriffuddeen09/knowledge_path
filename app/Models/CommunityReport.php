<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CommunityReport extends Model
{
    protected $fillable = [
        'community_id',
        'reporter_id',
        'reported_user_id',
        'reason',
        'details',
    ];

    public function community()
    {
        return $this->belongsTo(
            Community::class,
            'community_id'
        );
    }

    public function reporter()
    {
        return $this->belongsTo(
            User::class,
            'reporter_id'
        );
    }

    public function reportedUser()
    {
        return $this->belongsTo(
            User::class,
            'reported_user_id'
        );
    }
}