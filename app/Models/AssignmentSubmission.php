<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AssignmentSubmission extends Model
{
    protected $fillable = [
        'assignment_id',
        'student_id',
        'answers',
        'current_index',
        'remaining_seconds',
        'started_at',
        'submitted_at',
        'reschedule_count',
    ];

    protected $casts = [
        'answers' => 'array',
        'reschedule_due_at' => 'datetime',
        'submitted_at' => 'datetime',
        'started_at' => 'datetime',
    ];

    public function assignment()
    {
        return $this->belongsTo(Assignment::class);
    }

    public function student()
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function getEffectiveDueDateAttribute()
        {
            if ($this->extended_until) {
                return $this->extended_until;
            }

            return $this->assignment->due_at;
        }

}
