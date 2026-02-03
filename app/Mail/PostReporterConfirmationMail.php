<?php

namespace App\Mail;

use App\Models\PostReport;
use Illuminate\Mail\Mailable;

class PostReporterConfirmationMail extends Mailable
{
    public function __construct(public PostReport $report) {}

    public function build()
    {
        return $this
            ->subject('Report Received')
            ->view('emails.post_reporter_confirmation');
    }
}
