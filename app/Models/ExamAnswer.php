<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExamAnswer extends Model
{
    protected $fillable = [
    'exam_result_id', // ✅ REQUIRED
    'exam_id',
    'question_id',
    'student_id',
    'selected_answer',
];

    public function result()
    {
        return $this->belongsTo(ExamResult::class, 'exam_id');
    }
}

