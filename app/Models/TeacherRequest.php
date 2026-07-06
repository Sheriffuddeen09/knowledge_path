<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TeacherRequest extends Model
{
    protected $fillable = [

        'proposal_id',
        'student_id',
        'teacher_id',
        'teacher_form_id',
        'status',
        'teacher_deleted',
        'student_deleted',
    ];

    

    public function proposal()
    {
        return $this->belongsTo(Proposal::class);
    }

    public function student()
    {
        return $this->belongsTo(User::class,'student_id');
    }

    public function teacher()
    {
        return $this->belongsTo(User::class,'teacher_id');
    }

    public function teacherForm()
    {
        return $this->belongsTo(TeacherForm::class);
    }
    public function courseTitle()
    {
        return $this->belongsTo(CourseTitle::class, 'coursetitle_id');
    }
}