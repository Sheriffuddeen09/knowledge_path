<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Group extends Model
{
    protected $table = 'groups'; // 🔥 IMPORTANT (forces correct table)

    protected $primaryKey = 'id'; // 🔥 IMPORTANT for find()

    public $incrementing = true;

    protected $keyType = 'int';

    protected $fillable = [
        'name',
        'image',
        'created_by',
        'only_admin_send',
        'chat_id'
    ];
}