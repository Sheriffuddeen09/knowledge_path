<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SavedProduct extends Model
    {
    protected $fillable = ['user_id', 'data', 'status'];

    protected $casts = [
        'data' => 'array'
    ];
    }
