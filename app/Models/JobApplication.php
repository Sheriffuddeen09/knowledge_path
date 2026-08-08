<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class JobApplication extends Model
{
    use HasFactory;

    protected $fillable = [

        'job_post_id',

        'user_id',

        'cv',

        'additional_text',

        'qualification',

        'experience',

        'year_experience',

        'payment',

        'currency',

        'status',

        'reviewed_at',

    ];

    protected $casts = [

        'year_experience' => 'decimal:2',

        'payment' => 'decimal:2',

        'reviewed_at' => 'datetime',

    ];

    public function job()
    {
        return $this->belongsTo(
            JobPost::class,
            'job_post_id'
        );
    }

    public function user()
    {
        return $this->belongsTo(
            User::class
        );
    }
}