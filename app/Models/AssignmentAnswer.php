<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AssignmentAnswer extends Model
{
    protected $fillable = [
        'assignment_id',
        'question_id',
        'student_id',
        'selected_answer',
    ];

    public function question()
    {
        return $this->belongsTo(AssignmentQuestion::class, 'question_id');
    }
}

