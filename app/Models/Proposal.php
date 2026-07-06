<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;


    class Proposal extends Model
    {
        protected $fillable = [
            'student_id',
            'title',
            'subject',
            'price',
            'currency',
            'teacher_type',
            'teaching_mode',
            'preferred_location',
            'qualification',
            'teaching_hours',
            'description',
            'status',
            'from_time',
            'to_time',
            'expires_at',
        ];

        public function student()
        {
            return $this->belongsTo(User::class, 'student_id');
        }

        public function requests()
        {
            return $this->hasMany(
                TeacherRequest::class
            );
        }
    }