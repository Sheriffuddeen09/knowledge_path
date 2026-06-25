<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MeetingLink extends Model
{
    protected $fillable = [
        'room_id',
        'creator_id',
        'call_type',
        'expires_at'
    ];
}