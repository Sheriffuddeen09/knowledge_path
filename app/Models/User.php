<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Video;
use App\Models\VideoDownload;
use App\Models\LiveClassRequest;
use Illuminate\Support\Facades\Cache;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    // Relationships
    public function library() {
        return $this->belongsToMany(Video::class, 'libraries');
    }

    public function downloads() {
        return $this->hasMany(VideoDownload::class);
    }

    public function videos() {
        return $this->hasMany(Video::class, 'user_id');
    }

    public function liveRequestsSent() {
        return $this->hasMany(LiveClassRequest::class, 'user_id');
    }

    public function liveRequestsReceived() {
        return $this->hasMany(LiveClassRequest::class, 'teacher_id');
    }

    // Online status
    public function isOnline() {
        return Cache::has('user-is-online-' . $this->id);
    }

    // Mass assignable
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

    // Hidden attributes
    protected $hidden = [
        'password',
        'remember_token',
    ];

    // Casts
    protected $casts = [
        'email_verified_at' => 'datetime',
        'teacher_info' => 'array',
        'visibility' => 'array',
        'last_seen_at' => 'datetime',
    ];

    // Default attributes
    protected $attributes = [
        'visibility' => '{"email":true,"phone":true,"dob":true,"location":true,"gender":true}',
    ];
}
