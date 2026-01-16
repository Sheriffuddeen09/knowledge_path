<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;


class Chat extends Model
{
    protected $fillable = [
        'teacher_id',
        'student_id',
        'user_one_id',
        'user_two_id',
        'type',
        'status'
    ];

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

}
