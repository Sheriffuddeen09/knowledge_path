<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AssignmentAttempt extends Model
{
    use HasFactory;

    protected $fillable = [
        'assignment_id',
        'student_id',
        'answers',
        'current_index',
        'remaining_seconds',
        'started_at',
        'reschedule_count',
    ];

     protected $casts = [
        'answers' => 'array',
        'started_at' => 'datetime',
        'submitted_at' => 'datetime',
    ];

    public function assignment()
    {
        return $this->belongsTo(Assignment::class);
    }

    public function student()
    {
        return $this->belongsTo(User::class, 'student_id');
    }
}
