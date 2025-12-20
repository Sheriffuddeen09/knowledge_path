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
    
    // Scope to filter messages visible to a specific user
    public function users()
{
    return $this->belongsToMany(User::class, 'message_user')
        ->withPivot('deleted')
        ->withTimestamps();
}

public function scopeVisibleFor($query, $userId)
{
    return $query->whereHas('users', function ($q) use ($userId) {
        $q->where('user_id', $userId)
          ->where('deleted', false);
    });
}


}

 