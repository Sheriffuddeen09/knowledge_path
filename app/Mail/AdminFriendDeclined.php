<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use App\Models\AdminFriendRequest;


class AdminFriendDeclined extends Mailable

{
    use Queueable, SerializesModels;

    public $requestModel;

    public function __construct(AdminFriendRequest $requestModel)
    {
        $this->requestModel = $requestModel;
    }

    public function envelope(): \Illuminate\Mail\Mailables\Envelope
    {
        return new \Illuminate\Mail\Mailables\Envelope(
            subject: 'Your Friend Request Has Been Declined',
        );
    }

    public function content(): \Illuminate\Mail\Mailables\Content
    {
        return new \Illuminate\Mail\Mailables\Content(
            view: 'emails.admin-friend-declined',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
