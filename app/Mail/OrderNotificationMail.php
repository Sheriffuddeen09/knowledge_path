<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OrderNotificationMail extends Mailable
{
    public $order;
    public $type;

    public function __construct($order, $type)
    {
        $this->order = $order;
        $this->type = $type; // "created" or "paid"
    }

    public function build()
    {
        return $this->subject(
            $this->type === 'paid'
                ? 'Payment Confirmed - New Order Paid'
                : 'New Order Placed'
        )->view('emails.order-notification');
    }
}