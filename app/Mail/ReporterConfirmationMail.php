<?php

namespace App\Mail;

use App\Models\ChatReport;
use Illuminate\Mail\Mailable;

class ReporterConfirmationMail extends Mailable
{
    public function __construct(public ChatReport $report) {}

    public function build()
    {
        return $this
            ->subject('Report Received')
            ->view('emails.reporter_confirmation');
    }
}
