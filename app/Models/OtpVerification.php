<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OtpVerification extends Model
{
    use HasFactory;

    protected $fillable = [
        'email',
        'otp',
        'expired_at',
        'verified'
    ];

    protected $casts = [
        'expired_at' => 'datetime',
        'verified' => 'boolean',
    ];
}
