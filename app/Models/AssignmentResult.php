<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AssignmentResult extends Model
{
    protected $fillable = [
        'assignment_id',
        'student_id',
        'score',
        'total_questions',
        'is_late',
        'submitted_at',
    ];

    // 🧑 Student who submitted
    public function student()
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    // 👨‍🏫 Assignment (contains teacher_id)
    public function assignment()
    {
        return $this->belongsTo(Assignment::class);
    }

    // 📝 Answers (FILTERED BY STUDENT)
   public function answers()
        {
            return $this->hasMany(AssignmentAnswer::class, 'assignment_result_id');
        }


}
