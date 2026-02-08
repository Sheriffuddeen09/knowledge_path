<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HiddenStudentFriendRequest extends Model
{
    protected $fillable = [
        'user_id',
        'student_friend_request_id',
        'hidden_until',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function request()
    {
        return $this->belongsTo(StudentFriendRequest::class, 'student_friend_request_id');
    }
}
