<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MessageUser extends Model
{
    protected $table = 'message_user';

    protected $fillable = [
        'message_id',
        'user_id',
        'deleted',
        'updated_at'
    ];

    public $timestamps = true;
}