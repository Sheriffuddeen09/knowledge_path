<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TeacherForm extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'qualification',
        'experience',
        'specialization',
        'course_title',
        'course_payment',
        'currency',
        'compliment',
        'logo',
        'cv',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
