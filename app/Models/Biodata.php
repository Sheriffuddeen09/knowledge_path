<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Biodata extends Model
{
    protected $fillable = [
        'user_id',
        'marital_status',
        'address',
        'state',
        'bio',
    ];

    public function educations()
    {
        return $this->hasMany(Education::class);
    }

    public function careers()
    {
        return $this->hasMany(Career::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
    
}
