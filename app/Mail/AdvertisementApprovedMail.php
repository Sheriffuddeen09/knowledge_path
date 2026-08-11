<?php

namespace App\Mail;

use App\Models\Advertisement;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class AdvertisementApprovedMail extends Mailable
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
            ->subject('Advertisement Approved Successfully')
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

                    <h1 style="color: #16a34a;">
                        Congratulations!
                    </h1>

                    <p>
                        Your advertisement has been approved successfully.
                    </p>

                    <p>
                        You may now select how many users will be able
                        to view your advertisement.
                    </p>

                    <div style="
                        margin: 20px 0;
                        padding: 15px;
                        background-color: #f3f4f6;
                        border-radius: 8px;
                    ">

                        <p>
                            <strong>Advertisement Visibility Options:</strong>
                        </p>

                        <ul>
                            <li>50 badges = 1/4 of users</li>
                            <li>100 badges = 1/2 of users</li>
                            <li>200 badges = 3/4 of users</li>
                            <li>300 badges = All users</li>
                        </ul>

                    </div>

                    <p style="margin-top: 30px;">
                        <a
                            href="' . env('FRONTEND_URL') . '/advertisement/' . $this->advertisement->id . '"
                            style="
                                display: inline-block;
                                padding: 12px 20px;
                                background-color: #16a34a;
                                color: #ffffff;
                                text-decoration: none;
                                border-radius: 6px;
                                font-weight: bold;
                            "
                        >
                            Select Advertisement Visibility
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
