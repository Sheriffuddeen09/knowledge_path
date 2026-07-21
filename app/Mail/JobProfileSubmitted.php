<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
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