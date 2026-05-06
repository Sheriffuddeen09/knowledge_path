<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;


class Chat extends Model {
    protected $fillable = [
        'teacher_id',
        'student_id',
        'user_one_id',
        'user_two_id',
        'type',
        'status',
        'read_by',
        'name',
        'image',
        'created_by',
        'only_admin_send',
    ];

    protected $appends = ['image_url'];

    public function getImageUrlAttribute()
    {
        return $this->image
            ? asset('storage/' . $this->image)
            : null;
    }

    public function admins()
        {
            return $this->belongsToMany(User::class, 'chat_user')
                ->withPivot('role')
                ->wherePivot('role', 'admin');
        }

    public function members()
        {
            return $this->belongsToMany(User::class)
                ->withPivot('role', 'status')
                ->wherePivot('status', 'approved'); // 🔥 KEY FIX
        }

        
    public function isAdmin($userId)
        {
            return $this->admins()->where('user_id', $userId)->exists();
        }

    public function readBy()
        {
            return $this->belongsTo(User::class, 'read_by');
        }
        
    public function teacher()
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function student()
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function userOne()
    {
        return $this->belongsTo(User::class, 'user_one_id');
    }

    public function userTwo()
    {
        return $this->belongsTo(User::class, 'user_two_id');
    }

    public function messages()
    {
        return $this->hasMany(Message::class);
    }

    public function latestMessage()
    {
        return $this->hasOne(Message::class)->latestOfMany();
    }

    public function blocks()
    {
        return $this->hasMany(ChatBlock::class);
    }

    public function isBlockedFor($userId)
    {
        return $this->blocks()
            ->where('blocked_id', $userId)
            ->exists();
    }

    public function users(): BelongsToMany
        {
            return $this->belongsToMany(User::class, 'chat_user')
                ->withPivot(['role', 'last_read_message_id'])
                ->withTimestamps();
        }

}
