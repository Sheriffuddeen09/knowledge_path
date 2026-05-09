<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MessageFile extends Model
{
    protected $fillable = [
        'message_id',
        'file_url',
        'file_name',
        'type',
    ];

    public function message()
    {
        return $this->belongsTo(Message::class);
    }
}
