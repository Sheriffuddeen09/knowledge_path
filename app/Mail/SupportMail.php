<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class SupportMail extends Mailable
{

    public $data;


    public function __construct($data)
    {
        $this->data = $data;
    }


    public function envelope(): Envelope
    {
        return new Envelope(

            subject:"New Support Request"

        );
    }


    public function content(): Content
    {

        return new Content(

            view:"emails.support"

        );

    }

}