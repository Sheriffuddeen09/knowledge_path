<?php

namespace App\Mail;

use App\Models\MessageReport;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;   // ✅ THIS LINE
use Illuminate\Queue\SerializesModels;

class UserReportedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public MessageReport $report
    ) {}

    public function build()
    {
        return $this
            ->subject('Message Reported')
            ->view('emails.user_reported')
            ->with([
                'report' => $this->report,
            ]);
    }
}
