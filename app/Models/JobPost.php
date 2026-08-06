<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class JobPost extends Model
{
    use HasFactory;

    protected $fillable = [

        'user_id',

        'job_category_id',

        'title',

        'description',

        'about_us',

        'what_you_do',

        'location',

        'job_type',

        'currency',

        'payment',

        'payment_required',

        'employee_needed',

        'additional_compensation',

        'enable_qualification',

        'qualification',

        'enable_experience',

        'experience',

        'enable_year_experience',

        'year_experience',

        'status',

        'approved_by',

        'approved_at',

        'expire_date',

        'is_expired',

        'views',

        'application_count'

    ];

    protected $casts = [

        'approved_at'=>'datetime',

        'expire_date'=>'date',

        'payment_required'=>'boolean',

        'enable_qualification'=>'boolean',

        'enable_experience'=>'boolean',

        'enable_year_experience'=>'boolean',

        'is_expired'=>'boolean',

    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function category()
    {
        return $this->belongsTo(JobCategory::class,'job_category_id');
    }

    public function applications()
    {
        return $this->hasMany(JobApplication::class);
    }

    public function approver()
    {
        return $this->belongsTo(User::class,'approved_by');
    }

    public function skills()
    {
            return $this->belongsToMany(
                JobSkill::class,
                'job_post_skill'
            );
        }
    public function views()
    {
        return $this->hasMany(JobPostView::class);
    }
}