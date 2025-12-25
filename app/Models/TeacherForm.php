<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TeacherForm extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'coursetitle_id',
        'qualification',
        'experience',
        'specialization',
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
    public function coursetitle()
{
    return $this->belongsTo(Coursetitle::class);
}

}
