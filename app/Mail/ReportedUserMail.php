<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ReportedUserMail extends Mailable
{
    use Queueable, SerializesModels;

    public $videoTitle;

    public function __construct($videoTitle)
    {
        $this->videoTitle = $videoTitle;
    }

    public function build()
    {
        return $this->subject("You have been reported")
                    ->view('emails.reported_user')
                    ->with(['videoTitle' => $this->videoTitle]);
    }
}
