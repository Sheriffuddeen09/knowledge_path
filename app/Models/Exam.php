<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;


class Exam extends Model
{
    protected $fillable = [
        'teacher_id',
        'title',
        'questions',
        'duration_minutes',
        'due_at',
        'is_blocked'
    ];

    protected $casts = [
        'questions' => 'array',
        'due_at' => 'datetime',
        'is_blocked' => 'boolean',
    ];

    public function submissions()
    {
        return $this->hasMany(ExamSubmission::class);
    }

    public function teacher()
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function questions()
    {
        return $this->hasMany(ExamQuestion::class);
    }

    protected static function booted()
    {
        static::creating(function ($exam) {
            $exam->access_token = Str::uuid();
        });
    }

}

