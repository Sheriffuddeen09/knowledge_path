<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use \Illuminate\Database\Eloquent\SoftDeletes;


class Assignment extends Model
{

    use SoftDeletes;
    
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
        'is_blocked' => 'boolean'
    ];

    public function submissions()
    {
        return $this->hasMany(AssignmentSubmission::class);
    }

    public function teacher()
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function questions()
    {
        return $this->hasMany(AssignmentQuestion::class);
    }

    protected static function booted()
    {
        static::creating(function ($assignment) {
            $assignment->access_token = Str::uuid();
        });
    }

}

