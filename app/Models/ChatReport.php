<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChatReport extends Model
{
    protected $fillable = [
        'chat_id',
        'reporter_id',
        'reported_user_id',
        'reason',
        'details',
    ];

    public function reporter()
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }

    public function reportedUser()
    {
        return $this->belongsTo(User::class, 'reported_user_id');
    }

    public function chat()
    {
        return $this->belongsTo(Chat::class);
    }
}
