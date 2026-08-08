<?php

namespace App\Mail;

use App\Models\JobApplication;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class JobWithdrawnMail extends Mailable
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

        $userName =
            $application->user->first_name . ' ' .
            $application->user->last_name;

        $jobTitle =
            $application->job->title;

        $companyName =
            $application->job->user->jobProfile->company_name
            ?? 'the employer';

        return $this
            ->subject(
                'Job Opportunity Withdrawn - ' . $jobTitle
            )
            ->html('
                <!DOCTYPE html>

                <html>

                <head>

                    <meta charset="UTF-8">

                    <title>
                        Job Withdrawn
                    </title>

                </head>

                <body
                    style="
                        margin:0;
                        padding:0;
                        background:#f4f7fb;
                        font-family:Arial,sans-serif;
                    "
                >

                <table
                    width="100%"
                    cellpadding="0"
                    cellspacing="0"
                    style="padding:40px 15px;"
                >

                <tr>

                <td align="center">

                <table
                    width="600"
                    cellpadding="0"
                    cellspacing="0"
                    style="
                        max-width:600px;
                        background:#ffffff;
                        border-radius:20px;
                        overflow:hidden;
                    "
                >

                <!-- HEADER -->

                <tr>

                <td
                    style="
                        padding:35px;
                        background:#f59e0b;
                        color:#ffffff;
                        text-align:center;
                    "
                >

                    <h1 style="margin:0;">
                        Job Opportunity Withdrawn
                    </h1>

                </td>

                </tr>

                <!-- BODY -->

                <tr>

                <td style="padding:35px;">

                    <p>

                        Hello
                        <strong>' . e($userName) . '</strong>,

                    </p>

                    <p>

                        We are writing to inform you that the
                        job opportunity you applied for has been
                        withdrawn by
                        <strong>' . e($companyName) . '</strong>.

                    </p>

                    <div
                        style="
                            background:#fffbeb;
                            border-radius:15px;
                            padding:20px;
                            margin:25px 0;
                        "
                    >

                        <p>

                            <strong>Position:</strong>
                            ' . e($jobTitle) . '

                        </p>

                        <p style="margin-bottom:0;">

                            Your application for this position
                            is no longer active.

                        </p>

                    </div>

                    <p>

                        We apologize for any inconvenience this
                        may cause.

                    </p>

                    <p>

                        Please continue checking the platform
                        for other available opportunities.

                    </p>

                    <p style="margin-top:30px;">

                        Thank you for using our platform.

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