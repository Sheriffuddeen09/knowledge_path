<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class TeacherReview extends Model
{
    protected $fillable = [
        'teacher_id',
        'student_id',
        'teacher_request_id',
        'live_class_request_id',
        'rating',
        'review',
    ];

    public function liveClassRequest()
    {
        return $this->belongsTo(LiveClassRequest::class);
    }

    public function teacher()
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function student()
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function request()
    {
        return $this->belongsTo(TeacherRequest::class, 'teacher_request_id');
    }
}