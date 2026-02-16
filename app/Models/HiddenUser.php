<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HiddenUser extends Model
{
    protected $fillable = [
        'user_id',
        'hidden_user_id',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function hiddenUser()
    {
        return $this->belongsTo(User::class, 'hidden_user_id');
    }
}

