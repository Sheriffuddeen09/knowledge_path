<?php

namespace App\Mail;

use App\Models\TeacherRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class StudentCancelledRequestMail extends Mailable
{
    use Queueable, SerializesModels;

    public $request;

    public function __construct(TeacherRequest $request)
    {
        $this->request = $request;
    }

    public function build()
    {
        return $this->subject('Student Cancelled Teacher Request')
            ->view('emails.student_cancelled_request');
    }
}