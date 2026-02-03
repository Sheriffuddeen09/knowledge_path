<?php
namespace App\Mail;

use App\Models\CommentReport;
use Illuminate\Mail\Mailable;

class CommentReporterConfirmationMail extends Mailable
{
    public function __construct(public CommentReport $report) {}

    public function build()
    {
        return $this
            ->subject('Report Received')
            ->view('emails.comment_reporter_confirmation');
    }
}

