<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ReporterMail extends Mailable
{
    use Queueable, SerializesModels;

    public $videoTitle;

    public function __construct($videoTitle)
    {
        $this->videoTitle = $videoTitle;
    }

    public function build()
    {
        return $this->subject("Thanks for reporting a video")
                    ->view('emails.reporter')
                    ->with(['videoTitle' => $this->videoTitle]);
    }
}
