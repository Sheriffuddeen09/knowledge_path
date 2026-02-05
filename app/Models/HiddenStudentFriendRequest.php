<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HiddenStudentFriendRequest extends Model
{
    protected $fillable = [
        'student_id',
        'user_id',
        'hidden_until',
    ];

    /* ---------------- RELATIONSHIPS ---------------- */

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function student()
    {
        return $this->belongsTo(StudentFriendRequest::class);
    }
}