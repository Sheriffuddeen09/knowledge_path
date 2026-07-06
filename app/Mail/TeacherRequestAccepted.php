<?php

namespace App\Mail;

use App\Models\TeacherRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Content;

class TeacherRequestAccepted extends Mailable
{
    use Queueable, SerializesModels;

    public $teacherRequest;

    public function __construct(TeacherRequest $teacherRequest)
    {
        $this->teacherRequest = $teacherRequest;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '🎉 Your Proposal Request Has Been Accepted'
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.teacher-request-accepted',
            with: [
                'request' => $this->teacherRequest,
            ]
        );
    }
}