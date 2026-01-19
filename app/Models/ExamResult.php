<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExamResult extends Model
{
    protected $fillable = [
        'exam_id',
        'student_id',
        'score',
        'total_questions',
        'is_late',
        'submitted_at',
    ];

    public function student()
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function exam()
    {
        return $this->belongsTo(Exam::class);
    }

    // ✅ CORRECT
     public function answers()
        {
            return $this->hasMany(ExamAnswer::class, 'exam_result_id');
        }


}
