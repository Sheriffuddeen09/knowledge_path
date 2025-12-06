<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;  // ← import this
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;  // ← add HasApiTokens here

    public function library() {
        return $this->belongsToMany(Video::class, 'libraries');
    }

    protected $fillable = [
        'first_name',
        'last_name',
        'dob',
        'phone',
        'phone_country_code',
        'location',
        'location_country_code',
        'email',
        'gender',
        'role',
        'password',
        'email_verified_at',
        'admin_choice',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];
  
    protected $casts = [
        'email_verified_at' => 'datetime',
        'teacher_info' => 'array',   // JSON conversion
    ];
}
