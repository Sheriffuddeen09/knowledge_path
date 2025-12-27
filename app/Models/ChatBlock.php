<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;


   class ChatBlock extends Model
{
    protected $fillable = ['chat_id', 'blocker_id', 'blocked_id'];

    public function blocker()
    {
        return $this->belongsTo(User::class, 'blocker_id');
    }

    public function blocked()
    {
        return $this->belongsTo(User::class, 'blocked_id');
    }

}
