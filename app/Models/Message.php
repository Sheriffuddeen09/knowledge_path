<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Message extends Model
{
    protected $fillable = [
        'chat_id',
        'sender_id',
        'type',
        'message',
        'file',
        'edited',
        'delivered_at',
        'seen_at',
    ];

 

    protected $casts = [
        'seen_at' => 'datetime',
        'delivered_at' => 'datetime',
        'edited' => 'boolean'
    ];

    public function sender()
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function chat()
    {
        return $this->belongsTo(Chat::class);
    }

    public function reactions() {
        return $this->hasMany(MessageReaction::class);
    }
}
