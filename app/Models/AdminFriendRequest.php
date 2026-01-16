<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AdminFriendRequest extends Model

{
    protected $fillable = [
        'user_id',
        'admin_id',
        'status',
        'hidden_for_requester',
        'hidden_for_requested',
        'removed_until',
    ];

    public function requester()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function admin()
    {
        return $this->belongsTo(User::class, 'admin_id');
    }
}
