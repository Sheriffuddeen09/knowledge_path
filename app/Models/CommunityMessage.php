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
    'approval_status',
    'admin_response',
    'is_pinned',
    'poll_id',
    'is_forwarded',
    'forwarded_from',
    'forward_source',
    'forward_source_name',
    'forward_source_image',

    'forward_source_message_id',
    'forward_source_community_id',
    'is_system'
    ];

    public function poll()
    {
        return $this->belongsTo(
            CommunityPoll::class,
            'poll_id'
        );
    }
    
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
        public function approvals()
        {
            return $this->hasMany(
                CommunityMessageApproval::class,
                'message_id'
            );
        }

        
}