<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LiveClassRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'teacher_id',
        'status',
        'cleared_by_student',
        'cleared_by_teacher',
    ];

    // Student who sent the request
    public function student()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    // Teacher who received the request
    public function teacher()
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }
}
