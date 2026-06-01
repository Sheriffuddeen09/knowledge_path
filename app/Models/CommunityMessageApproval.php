<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CommunityMessageApproval extends Model
{
    protected $fillable = [
        'message_id',
        'admin_id',
        'admin_response',
        'status',
    ];

    public function message()
    {
        return $this->belongsTo(CommunityMessage::class, 'message_id');
    }
}