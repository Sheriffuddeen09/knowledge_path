<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use Carbon\Carbon;

class Job extends Model
{

    protected $fillable=[

        "user_id",
        "job_category_id",
        "title",
        "job_type",
        "location",
        "payment",
        'currency',
        "expire_date",
        "objective",
        "description",
        "active"

    ];


    protected $casts=[

        'expire_date'=>'date'

    ];


    public function creator()
    {
        return $this->belongsTo(User::class,'user_id');
    }


    public function category()
    {
        return $this->belongsTo(
            JobCategory::class,
            'job_category_id'
        );
    }


    public function applications()
    {
        return $this->hasMany(
            JobApplication::class
        );
    }


    public function isExpired()
    {
        return $this->expire_date
        ->isPast();
    }


}