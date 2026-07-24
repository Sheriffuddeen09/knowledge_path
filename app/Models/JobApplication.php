<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JobApplication extends Model
{

    protected $fillable=[

        'job_id',
        'job_finder_id',
        'message',
        'status'

    ];


    public function job()
    {
        return $this->belongsTo(Job::class);
    }


    public function finder()
    {
        return $this->belongsTo(
            User::class,
            'job_finder_id'
        );
    }


}