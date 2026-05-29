<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CommunityMessage extends Model
{

    use SoftDeletes;

    protected $dates = [
        'deleted_at',
    ];
    
    protected $fillable = [
        'community_id',
        'sender_id',
        'message',
        'type',
        'file',
        'replied_to',
        'response_mode',
    ];

    public function sender()
    {
        return $this->belongsTo(User::class);
    }

    public function community()
    {
        return $this->belongsTo(
            Community::class
        );
    }

    public function reactions()
        {
        return $this->hasMany(
            CommunityMessageReaction::class,
            'community_message_id'
        );
        }

        public function repliedTo()
        {
            return $this->belongsTo(CommunityMessage::class, 'replied_to');
        }

        public function repliedMessage()
            {
                return $this->belongsTo(
                    CommunityMessage::class,
                    'replied_to'
                );
            }
}