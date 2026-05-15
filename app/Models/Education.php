<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Education extends Model
{
    protected $table = 'educations';

    protected $fillable = [
        'biodata_id',
        'school',
        'course',
        'year',
    ];

    public function biodata()
    {
        return $this->belongsTo(Biodata::class);
    }
}