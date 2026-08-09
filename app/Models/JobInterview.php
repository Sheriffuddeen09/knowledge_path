<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class JobInterview extends Model
{
    use HasFactory;

        protected $fillable = [

            'job_application_id',

            'interview_token',

            'interview_date',

            'interview_time',

            'meeting_link',

            'call_link',

            'status',

            'notes',

        ];

    protected $casts = [
        'interview_date' => 'date',
        'interview_time' => 'datetime:H:i',
    ];

    public function application()
    {
        return $this->belongsTo(
            JobApplication::class,
            'job_application_id'
        );
    }
}