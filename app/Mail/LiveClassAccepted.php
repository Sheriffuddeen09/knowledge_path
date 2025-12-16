<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use App\Models\LiveClassRequest;

class LiveClassAccepted extends Mailable
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
            subject: 'Your Live Class Request Has Been Accepted',
        );
    }

    public function content(): \Illuminate\Mail\Mailables\Content
    {
        return new \Illuminate\Mail\Mailables\Content(
            view: 'emails.live-class-accepted',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
