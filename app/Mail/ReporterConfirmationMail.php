<?php

namespace App\Mail;

use App\Models\ChatReport;
use App\Models\CommunityReport;
use Illuminate\Mail\Mailable;

class ReporterConfirmationMail extends Mailable
{
    public function __construct(
        public ChatReport|CommunityReport $report
    ) {}

    public function build()
    {
        return $this
            ->subject('Report Received')
            ->view('emails.reporter_confirmation');
    }
}