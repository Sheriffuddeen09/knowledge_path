<?php

namespace App\Mail;

use App\Models\PostReport;
use Illuminate\Mail\Mailable;

class PostReportedMail extends Mailable
{
    public function __construct(public PostReport $report) {}

    public function build()
    {
        return $this
            ->subject('You Have Been Reported')
            ->view('emails.post_reported');
    }
}
