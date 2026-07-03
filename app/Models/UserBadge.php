<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserBadge extends Model
{
    protected $fillable = [
        'user_id',
        'badges',
        'source',
        'result_id',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}