<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;


class Chat extends Model
{
    protected $fillable = ['teacher_id','student_id', 'type', 'status'];

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
        return $this->belongsToMany(User::class, 'chat_user', 'chat_id', 'user_id');
    }
     public function latestMessage() {
        return $this->hasOne(Message::class)->latestOfMany();
    }

    public function participants()
        {
            return $this->belongsToMany(User::class, 'chat_user');
        }
        public function blocks()
        {
            return $this->hasMany(ChatBlock::class);
        }

    public function isBlockedFor($userId)
        {
            return $this->blocks()
                ->where('blocked_id', $userId)
                ->exists();
        }


}
$chats = Chat::with('latestMessage')->get();
