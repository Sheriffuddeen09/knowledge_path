<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Career extends Model
{
    protected $table = 'careers';
    
    protected $fillable = [
        'biodata_id',
        'company',
        'role',
        'duration'
    ];

    public function biodata()
    {
        return $this->hasMany(Biodata::class);
    }
}