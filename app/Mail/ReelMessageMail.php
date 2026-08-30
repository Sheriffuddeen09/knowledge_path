<?php

namespace App\Mail;

use App\Models\Post;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ReelMessageMail extends Mailable
{
    use Queueable, SerializesModels;

    public Post $reel;
    public User $sender;
    public string $messageText;

    public function __construct(
        Post $reel,
        User $sender,
        string $messageText
    ) {
        $this->reel = $reel;
        $this->sender = $sender;
        $this->messageText = $messageText;
    }

    public function build()
    {
        $posterName = trim(
            ($this->reel->user->first_name ?? '') .
            ' ' .
            ($this->reel->user->last_name ?? '')
        );

        $senderName = trim(
            ($this->sender->first_name ?? '') .
            ' ' .
            ($this->sender->last_name ?? '')
        );

        $frontendUrl = rtrim(
            env('FRONTEND_URL'),
            '/'
        );

        return $this
            ->to($this->reel->user->email)
            ->subject(
                $senderName .
                ' sent you a message about your reel'
            )
            ->html('
                <!DOCTYPE html>

                <html>

                <head>

                    <meta charset="UTF-8">

                    <meta
                        name="viewport"
                        content="width=device-width, initial-scale=1.0"
                    >

                    <title>
                        Reel Message
                    </title>

                </head>

                <body
                    style="
                        margin:0;
                        padding:0;
                        background:#f4f4f4;
                        font-family:Arial,Helvetica,sans-serif;
                    "
                >

                    <div
                        style="
                            max-width:600px;
                            margin:40px auto;
                            background:#ffffff;
                            border-radius:12px;
                            overflow:hidden;
                            box-shadow:0 2px 10px rgba(0,0,0,0.08);
                        "
                    >

                        <!-- HEADER -->

                        <div
                            style="
                                background:#16a34a;
                                padding:25px;
                                text-align:center;
                            "
                        >

                            <h1
                                style="
                                    margin:0;
                                    color:#ffffff;
                                    font-size:24px;
                                "
                            >
                                New Reel Message
                            </h1>

                        </div>


                        <!-- CONTENT -->

                        <div
                            style="
                                padding:30px;
                                color:#333333;
                            "
                        >

                            <h2
                                style="
                                    margin-top:0;
                                    font-size:20px;
                                "
                            >
                                Hello ' .
                                e($posterName) .
                                ',
                            </h2>


                            <p
                                style="
                                    font-size:16px;
                                    line-height:1.6;
                                "
                            >
                                <strong>' .
                                e($senderName) .
                                '</strong>
                                sent you a message about your reel.
                            </p>


                            <!-- MESSAGE -->

                            <div
                                style="
                                    margin:25px 0;
                                    padding:20px;
                                    background:#f0fdf4;
                                    border-left:4px solid #16a34a;
                                    border-radius:6px;
                                "
                            >

                                <p
                                    style="
                                        margin:0 0 8px;
                                        font-size:14px;
                                        color:#666666;
                                    "
                                >
                                    Message
                                </p>

                                <p
                                    style="
                                        margin:0;
                                        font-size:16px;
                                        line-height:1.6;
                                        color:#333333;
                                        white-space:pre-wrap;
                                    "
                                >' .
                                    nl2br(
                                        e(
                                            $this->messageText
                                        )
                                    ) .
                                '</p>

                            </div>


                            <p
                                style="
                                    font-size:15px;
                                    line-height:1.6;
                                    color:#555555;
                                "
                            >
                                You can open your reels to view
                                the conversation and reply to
                                the message.
                            </p>


                            <!-- BUTTON -->

                            <div
                                style="
                                    text-align:center;
                                    margin:30px 0;
                                "
                            >

                                <a
                                    href="' .
                                    e(
                                        $frontendUrl .
                                        '/'
                                    ) .
                                    '"
                                    style="
                                        display:inline-block;
                                        padding:12px 24px;
                                        background:#16a34a;
                                        color:#ffffff;
                                        text-decoration:none;
                                        border-radius:8px;
                                        font-weight:bold;
                                    "
                                >
                                    View Your Reels
                                </a>

                            </div>


                            <p
                                style="
                                    font-size:14px;
                                    color:#777777;
                                    line-height:1.5;
                                "
                            >
                                Thank you for being part of
                                the community.
                            </p>

                        </div>


                        <!-- FOOTER -->

                        <div
                            style="
                                padding:20px;
                                text-align:center;
                                background:#f9fafb;
                                color:#888888;
                                font-size:12px;
                            "
                        >

                            This is an automated notification.
                            Please do not reply to this email.

                        </div>

                    </div>

                </body>

                </html>
            ');
    }
}