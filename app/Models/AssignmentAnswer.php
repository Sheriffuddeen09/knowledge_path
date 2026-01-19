<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AssignmentAnswer extends Model
{
    protected $fillable = [
    'assignment_result_id', // ✅ REQUIRED
    'assignment_id',
    'question_id',
    'student_id',
    'selected_answer',
];


    public function result()
    {
        return $this->belongsTo(AssignmentResult::class, 'assignment_id');
    }
}

