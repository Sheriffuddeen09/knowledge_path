<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StudentFriendRequest extends Model
{
    protected $fillable = [
        'user_id',
        'student_id',
        'status',
        'hidden_for_requester',
        'hidden_for_requested',
        'removed_until',
    ];

    public function requester()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function student()
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }



}
