<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Message extends Model
{
    protected $fillable = [
        'chat_id',
        'sender_id',
        'receiver_id',
        'type',
        'message',
        'file',
        'edited',
        'forwarded_from',
        'replied_to',
        'user_id',
        'file_name',
        'read_by',
        'is_pinned',
        'group_id',
        'is_forwarded',
        'expires_at',
        'delivered_at',
        'read_at',
        'iv',
        'forward_source',
        'forward_source_name',
        'forward_source_image',

        'forward_source_message_id',
        'forward_source_community_id',
    ];

    protected $casts = [
        'files'        => 'array',
        'delivered_at' => 'datetime',
        'expires_at'   => 'datetime',
        'read_at'      => 'datetime',
        'edited'       => 'boolean',
        'is_forwarded' => 'boolean',
        'replied_to'   => 'array', 
    ];

    public function forwardedFrom()
    {
    return $this->belongsTo(CommunityMessage::class, 'forwarded_from');
    }

    public function getForwardSourceNameAttribute()
    {
        return $this->forwardedFrom?->community?->community_name
            ?? 'Unknown community';
    }

    public function getForwardSourceImageAttribute()
    {
        return $this->forwardedFrom?->community?->community_image;
    }
    public function messageUsers()
    {
        return $this->hasMany(MessageUser::class, 'message_id');
    }
    
    public function files()
    {
        return $this->hasMany(MessageFile::class);
    }

    public function forwardedMessage()
    {
        return $this->belongsTo(Message::class, 'forwarded_from');
    }

    public function reader()
    {
        return $this->belongsTo(User::class, 'read_by');
    }

    public function readBy()
    {
        return $this->belongsTo(User::class, 'read_by');
    }

    public function downloads()
    {
        return $this->hasMany(MessageDownload::class);
    }

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
    
    public function users()
    {
        return $this->belongsToMany(User::class, 'message_user')
            ->withPivot(['deleted', 'seen_at'])
            ->withTimestamps();
    }

    public function scopeVisibleFor($query, $userId)
    {
        return $query->whereHas('users', function ($q) use ($userId) {
            $q->where('user_id', $userId)
              ->where('deleted', false);
        });
    }

    protected $appends = ['file_url'];

    public function getFileUrlAttribute()
    {
        return $this->file ? asset('storage/' . $this->file) : null;
    }

    public function scopeActive($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('expires_at')
              ->orWhere('expires_at', '>', now());
        });
    }
}
