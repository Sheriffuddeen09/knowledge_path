<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class JobPostView extends Model
{
    use HasFactory;

    protected $fillable = [

        'job_post_id',

        'user_id',

        'ip_address',

        'device',

        'browser',

        'platform',

        'country',

        'city',

        'referrer',

        'viewed_at'

    ];

    protected $casts = [

        'viewed_at' => 'datetime',

    ];

    public function job()
    {
        return $this->belongsTo(JobPost::class,'job_post_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}