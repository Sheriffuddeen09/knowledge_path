<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Passkey extends Model
{
    protected $fillable = [
        'user_id',
        'credential_id',
        'public_key',
        'name'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}