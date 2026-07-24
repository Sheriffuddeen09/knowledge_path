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
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;


class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    public function jobProfile()
    {
    return $this->hasOne(JobProfile::class);
    }
    public function teacherReviews()
    {
        return $this->hasMany(TeacherReview::class, 'teacher_id');
    }

    public function studentReviews()
    {
        return $this->hasMany(TeacherReview::class, 'student_id');
    }


        public function jobs()
        {
            return $this->hasMany(
                Job::class
            );
        }


        public function jobApplications()
        {
            return $this->hasMany(
                JobApplication::class,
                'job_finder_id'
            );
        }

    public function teacherRequests()
    {
    return $this->hasMany(
        TeacherRequest::class,
        'teacher_id'
    );
    }

    public function studentRequests()
        {
            return $this->hasMany(
                TeacherRequest::class,
                'student_id'
            );
        }

    public function badges()
        {
            return $this->hasMany(UserBadge::class);
        }

    public function passkeys()
        {
            return $this->hasMany(Passkey::class);
        }

    public function communities()
{
    return $this->belongsToMany(
        Community::class,
        'community_members'
    )
    ->withPivot([
        'role',
        'can_message',
        'muted',
        'joined_at',
        'membership_status',
        'last_read_message_id',
    ])
    ->withTimestamps();
}
    
    public function chats()
        {
            return $this->belongsToMany(Chat::class, 'chat_user')
                ->withPivot(['role', 'last_read_message_id'])
                ->withTimestamps();
        }
    public function library()
    {
        return $this->belongsToMany(Post::class, 'post_saves')
                    ->withTimestamps();
    }

    public function proposals()
    {
        return $this->hasMany(Proposal::class, 'student_id');
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
    
    public function hiddenUsers()
    {
        return $this->hasMany(HiddenUser::class);
    }

    public function hiddenByUsers()
    {
        return $this->hasMany(HiddenUser::class, 'hidden_user_id');
    }


    public function isOnline()
    {
        if (!$this->last_seen_at) return false;

        return Carbon::parse($this->last_seen_at)->diffInMinutes(now()) < 2;
    }

    public function acceptedStudentFriends()
    {
    return $this->belongsToMany(
        User::class,
        'student_friend_requests',
        'user_id',
        'student_id'
    )->wherePivot('status', 'accepted');
    }

    public function acceptedAdminFriends()
    {
        return $this->belongsToMany(
            User::class,
            'admin_friend_requests',
            'user_id',
            'admin_id'
        )->wherePivot('status', 'accepted');
    }

    public function allFriendIds()
{
    $studentFriends = \DB::table('student_friend_requests')
        ->where('status', 'accepted')
        ->where(function ($q) {
            $q->where('user_id', $this->id)
              ->orWhere('student_id', $this->id);
        })
        ->get();

    $studentIds = $studentFriends->map(function ($row) {
        return $row->user_id == $this->id
            ? $row->student_id
            : $row->user_id;
    });

    $adminFriends = \DB::table('admin_friend_requests')
        ->where('status', 'accepted')
        ->where(function ($q) {
            $q->where('user_id', $this->id)
              ->orWhere('admin_id', $this->id);
        })
        ->get();

    $adminIds = $adminFriends->map(function ($row) {
        return $row->user_id == $this->id
            ? $row->admin_id
            : $row->user_id;
    });

    return $studentIds
        ->merge($adminIds)
        ->unique()
        ->values();
}



    // Mass assignable
    protected $fillable = [
        'first_name',
        'last_name',
        'dob',
        'phone',
        'phone_country_code',
        'location',
        'email',
        'gender',
        'role',
        'password',
        'email_verified_at',
        'teacher_profile_completed',
        'teacher_info',
        'last_seen_at',
        'address',
        'city',
        'state',
        'zip',
        'privacy'
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
