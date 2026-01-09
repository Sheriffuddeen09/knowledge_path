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

    // 🧑 Student who submitted
    public function student()
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    // 👨‍🏫 Assignment (contains teacher_id)
    public function exam()
    {
        return $this->belongsTo(Exam::class);
    }

    // 📝 Answers (FILTERED BY STUDENT)
    public function answers()
    {
        return $this->hasMany(ExamAnswer::class, 'exam_id', 'exam_id')
            ->where('student_id', $this->student_id);
    }
}
