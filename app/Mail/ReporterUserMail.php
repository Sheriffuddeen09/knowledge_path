<?php

namespace App\Mail;

use App\Models\MessageReport;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ReporterUserMail extends Mailable
{
    use Queueable, SerializesModels;

    public MessageReport $report;

    public function __construct(MessageReport $report)
    {
        $this->report = $report;
    }

    public function build()
    {
        return $this
            ->subject('Thank you for your report')
            ->view('emails.user_reporter')
            ->with([
                'report' => $this->report,
            ]);
    }
}
