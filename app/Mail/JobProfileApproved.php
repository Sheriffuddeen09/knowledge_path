<?php

namespace App\Mail;
use App\Models\JobProfile;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Bus\Queueable;
class JobProfileApproved extends Mailable
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
 ->subject('Your Job Profile Has Been Approved')
 ->view('emails.job-profile-approved');
 }
}