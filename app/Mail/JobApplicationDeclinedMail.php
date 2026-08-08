<?php

namespace App\Mail;

use App\Models\JobApplication;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class JobApplicationDeclinedMail extends Mailable
{
    use Queueable, SerializesModels;

    public JobApplication $application;

    public function __construct(
        JobApplication $application
    ) {
        $this->application = $application;
    }

    public function build()
    {
        $application = $this->application;

        $userName = trim(
            ($application->user->first_name ?? '') . ' ' .
            ($application->user->last_name ?? '')
        );

        $jobTitle =
            $application->job->title ?? 'Job Position';

        $companyName =
            $application->job->user->jobProfile->company_name
            ?? 'the employer';

        return $this
            ->subject(
                'Update Regarding Your Job Application'
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
                        Job Application Update
                    </title>

                </head>

                <body
                    style="
                        margin:0;
                        padding:0;
                        background:#f4f7fb;
                        font-family:Arial,Helvetica,sans-serif;
                    "
                >

                <table
                    width="100%"
                    cellpadding="0"
                    cellspacing="0"
                    border="0"
                    style="
                        width:100%;
                        padding:40px 15px;
                        background:#f4f7fb;
                    "
                >

                <tr>

                <td align="center">

                <table
                    width="600"
                    cellpadding="0"
                    cellspacing="0"
                    border="0"
                    style="
                        width:100%;
                        max-width:600px;
                        background:#ffffff;
                        border-radius:20px;
                        overflow:hidden;
                        box-shadow:0 5px 20px rgba(0,0,0,0.05);
                    "
                >

                <!-- HEADER -->

                <tr>

                <td
                    style="
                        padding:35px 25px;
                        background:#dc2626;
                        color:#ffffff;
                        text-align:center;
                    "
                >

                    <div
                        style="
                            width:60px;
                            height:60px;
                            margin:0 auto 15px;
                            border-radius:50%;
                            background:rgba(255,255,255,0.15);
                            line-height:60px;
                            font-size:28px;
                        "
                    >
                        !
                    </div>

                    <h1
                        style="
                            margin:0;
                            font-size:26px;
                        "
                    >
                        Application Update
                    </h1>

                    <p
                        style="
                            margin:10px 0 0;
                            color:#fee2e2;
                            font-size:14px;
                        "
                    >
                        Update regarding your job application
                    </p>

                </td>

                </tr>

                <!-- BODY -->

                <tr>

                <td style="padding:35px 30px;">

                    <p
                        style="
                            margin:0 0 18px;
                            font-size:16px;
                            color:#1f2937;
                        "
                    >

                        Hello
                        <strong>
                            ' . e($userName) . '
                        </strong>,

                    </p>

                    <p
                        style="
                            margin:0 0 20px;
                            font-size:15px;
                            line-height:1.7;
                            color:#4b5563;
                        "
                    >

                        Thank you for taking the time to apply
                        for the
                        <strong>
                            ' . e($jobTitle) . '
                        </strong>
                        position at
                        <strong>
                            ' . e($companyName) . '
                        </strong>.

                    </p>

                    <!-- NOTICE -->

                    <div
                        style="
                            background:#fef2f2;
                            border:1px solid #fecaca;
                            border-radius:15px;
                            padding:20px;
                            margin:25px 0;
                        "
                    >

                        <p
                            style="
                                margin:0;
                                font-size:15px;
                                line-height:1.6;
                                color:#991b1b;
                            "
                        >

                            We regret to inform you that your
                            application was not selected for this
                            position.

                        </p>

                    </div>

                    <p
                        style="
                            margin:0 0 18px;
                            font-size:15px;
                            line-height:1.7;
                            color:#4b5563;
                        "
                    >

                        We appreciate your interest and the effort
                        you put into your application.

                    </p>

                    <p
                        style="
                            margin:0 0 25px;
                            font-size:15px;
                            line-height:1.7;
                            color:#4b5563;
                        "
                    >

                        Please continue checking the platform
                        for other opportunities that may be a
                        better match for your skills and experience.

                    </p>

                    <!-- STATUS -->

                    <div
                        style="
                            background:#f8fafc;
                            border-radius:15px;
                            padding:18px 20px;
                            margin:25px 0;
                        "
                    >

                        <p
                            style="
                                margin:0 0 8px;
                                font-size:13px;
                                color:#6b7280;
                            "
                        >
                            Application Status
                        </p>

                        <strong
                            style="
                                color:#dc2626;
                                font-size:16px;
                            "
                        >
                            Declined
                        </strong>

                    </div>

                    <p
                        style="
                            margin:25px 0 0;
                            font-size:15px;
                            line-height:1.7;
                            color:#4b5563;
                        "
                    >

                        We wish you the very best in your
                        job search and future career.

                    </p>

                    <p
                        style="
                            margin:25px 0 0;
                            font-size:15px;
                            color:#1f2937;
                        "
                    >

                        Best regards,<br>

                        <strong>
                            ' . e($companyName) . '
                        </strong>

                    </p>

                </td>

                </tr>

                <!-- FOOTER -->

                <tr>

                <td
                    style="
                        padding:20px 30px;
                        background:#f8fafc;
                        text-align:center;
                        border-top:1px solid #e5e7eb;
                    "
                >

                    <p
                        style="
                            margin:0;
                            font-size:12px;
                            color:#9ca3af;
                        "
                    >

                        This is an automated notification.
                        Please do not reply directly to this email.

                    </p>

                </td>

                </tr>

                </table>

                </td>

                </tr>

                </table>

                </body>

                </html>
            ');
    }
}