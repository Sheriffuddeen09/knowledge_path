<?php

namespace App\Mail;

use App\Models\JobProfile;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class JobProfileSubmitted extends Mailable
{
    use Queueable, SerializesModels;

    public $profile;

    public function __construct(JobProfile $profile)
    {
        $this->profile = $profile;
    }

    public function build()
    {
        return $this
            ->subject('New Job Profile Submitted')
            ->view('emails.job-profile-submitted');
    }
}