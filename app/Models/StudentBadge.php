<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;



class StudentBadge extends Model
{
    protected $fillable = [
        'student_id',
        'badges',
        'source',
        'result_id',
    ];

    public function student()
    {
        return $this->belongsTo(User::class, 'student_id');
    }
}
