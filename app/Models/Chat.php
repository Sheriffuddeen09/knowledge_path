<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;


class Chat extends Model
{
    protected $fillable = ['teacher_id','student_id', 'type'];

    public function messages() {
        return $this->hasMany(Message::class);
    }

    public function teacher() {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function student() {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function users()
    {
        return $this->belongsToMany(User::class, 'chat_user');
    }
     public function latestMessage() {
        return $this->hasOne(Message::class)->latestOfMany();
    }

}
$chats = Chat::with('latestMessage')->get();
