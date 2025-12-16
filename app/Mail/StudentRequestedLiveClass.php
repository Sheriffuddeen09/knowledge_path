<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use App\Models\LiveClassRequest;

class StudentRequestedLiveClass extends Mailable
{
    use Queueable, SerializesModels;

    public $requestModel;

    public function __construct(LiveClassRequest $requestModel)
    {
        $this->requestModel = $requestModel;
    }

    public function envelope(): \Illuminate\Mail\Mailables\Envelope
    {
        return new \Illuminate\Mail\Mailables\Envelope(
            subject: 'New Live Class Request Received',
        );
    }

    public function content(): \Illuminate\Mail\Mailables\Content
    {
        return new \Illuminate\Mail\Mailables\Content(
            view: 'emails.student-requested-live-class',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
