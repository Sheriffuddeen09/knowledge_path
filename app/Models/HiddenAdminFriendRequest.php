<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HiddenAdminFriendRequest extends Model

{
    protected $fillable = [
        'admin_id',
        'user_id',
        'hidden_until',
    ];

    /* ---------------- RELATIONSHIPS ---------------- */

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function adminRequest()
    {
        return $this->belongsTo(AdminFriendRequest::class);
    }
}

