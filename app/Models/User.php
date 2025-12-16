<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Video;
use App\Models\VideoDownload; // ← import this

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    // User library (saved videos)
    public function library() {
        return $this->belongsToMany(Video::class, 'libraries');
    }

    // User downloaded videos
    public function downloads() {
        return $this->hasMany(VideoDownload::class);
    }

    // User.php
    public function videos()
    {
        return $this->hasMany(Video::class, 'user_id'); // or your correct foreign key
    }


    public function liveRequestsSent()
    {
        return $this->hasMany(LiveClassRequest::class, 'user_id');
    }

    public function liveRequestsReceived()
    {
        return $this->hasMany(LiveClassRequest::class, 'teacher_id');
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
        'teacher_info' => 'array',
        'visibility' => 'array',
    ];
    protected $attributes = [
    'visibility' => '{"email":true,"phone":true,"dob":true,"location":true,"gender":true}',
    ];
}
