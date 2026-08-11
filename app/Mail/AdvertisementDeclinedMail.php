<?php

namespace App\Mail;

use App\Models\Advertisement;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class AdvertisementDeclinedMail extends Mailable
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
            ->subject('Advertisement Declined')
            ->html('
                <!DOCTYPE html>
                <html>
                <body style="
                    font-family: Arial, sans-serif;
                    line-height: 1.6;
                    color: #333;
                    margin: 0;
                    padding: 20px;
                ">

                    <h1 style="color: #dc2626;">
                        Advertisement Declined
                    </h1>

                    <p>
                        Unfortunately, your advertisement has not been
                        approved at this time.
                    </p>

                    <p>
                        <strong>Reason:</strong>
                    </p>

                    <div style="
                        margin: 15px 0;
                        padding: 15px;
                        background-color: #fef2f2;
                        border-left: 4px solid #dc2626;
                        border-radius: 6px;
                    ">
                        ' . nl2br(e($this->advertisement->decline_reason)) . '
                    </div>

                    <p>
                        You may edit and resubmit your advertisement.
                    </p>

                    <p style="margin-top: 30px;">
                        <a
                            href="' . env('FRONTEND_URL') . '/advertisement/edit/' . $this->advertisement->id . '"
                            style="
                                display: inline-block;
                                padding: 12px 20px;
                                background-color: #2563eb;
                                color: #ffffff;
                                text-decoration: none;
                                border-radius: 6px;
                                font-weight: bold;
                            "
                        >
                            Edit Advertisement
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
