<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class JobApplication extends Model
{
    use HasFactory;

    protected $fillable = [

        'job_post_id',

        'user_id',

        'job_owner_id',

        'cover_letter',

        'resume',

        'currency',

        'expected_salary',

        'status',

        'remark',

        'reviewed_by',

        'reviewed_at',

    ];

    protected $casts = [

        'reviewed_at' => 'datetime',

    ];

    public function job()
    {
        return $this->belongsTo(JobPost::class,'job_post_id');
    }

    public function applicant()
    {
        return $this->belongsTo(User::class,'user_id');
    }

    public function owner()
    {
        return $this->belongsTo(User::class,'job_owner_id');
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class,'reviewed_by');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}