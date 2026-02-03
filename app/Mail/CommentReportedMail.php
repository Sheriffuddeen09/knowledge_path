<?php

namespace App\Mail;

use App\Models\CommentReport;
use Illuminate\Mail\Mailable;

class CommentReportedMail extends Mailable
{
    public function __construct(public CommentReport $report) {}

    public function build()
    {
        return $this
            ->subject('You Have Been Reported')
            ->view('emails.comment_reported');
    }
}
