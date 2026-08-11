<?php

namespace App\Mail;

use App\Models\Advertisement;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class AdvertisementPendingMail extends Mailable
{
    use Queueable, SerializesModels;

    public $advertisement;

    public function __construct(Advertisement $advertisement)
    {
        $this->advertisement = $advertisement;
    }

    public function build()
    {
        return $this
            ->subject('New Advertisement Request')
            ->html('
                <!DOCTYPE html>
                <html>
                <body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">

                    <h1 style="color: #2563eb;">
                        New Advertisement Request
                    </h1>

                    <p>
                        A new advertisement has been submitted and is waiting
                        for your review.
                    </p>

                    <p>
                        <strong>Title:</strong><br>
                        ' . e($this->advertisement->title) . '
                    </p>

                    <p>
                        <strong>Type:</strong><br>
                        ' . e($this->advertisement->type) . '
                    </p>

                    <p>
                        <strong>Description:</strong><br>
                        ' . nl2br(e($this->advertisement->description)) . '
                    </p>

                    ' . ($this->advertisement->link ? '
                    <p>
                        <strong>Link:</strong><br>
                        <a href="' . e($this->advertisement->link) . '">
                            ' . e($this->advertisement->link) . '
                        </a>
                    </p>
                    ' : '') . '

                    <p style="margin-top: 30px;">
                        <a
                            href="' . env('FRONTEND_URL') . '/admin/advertisement"
                            style="
                                display: inline-block;
                                padding: 12px 20px;
                                background-color: #2563eb;
                                color: #ffffff;
                                text-decoration: none;
                                border-radius: 6px;
                            "
                        >
                            Review Advertisement
                        </a>
                    </p>

                    <p style="margin-top: 30px;">
                        Thank you.
                    </p>

                </body>
                </html>
            ');
    }
}
