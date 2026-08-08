<?php

namespace App\Mail;

use App\Models\JobApplication;
use App\Models\JobInterview;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class JobApplicationAcceptedMail extends Mailable
{
    use Queueable, SerializesModels;

    public JobApplication $application;

    public JobInterview $interview;

    public function __construct(
        JobApplication $application,
        JobInterview $interview
    ) {
        $this->application = $application;
        $this->interview = $interview;
    }

    public function build()
    {
        $application = $this->application;
        $interview = $this->interview;

        $userName =
            $application->user->first_name . ' ' .
            $application->user->last_name;

        $jobTitle =
            $application->job->title;

        $companyName =
            $application->job->user->jobProfile->company_name
            ?? 'the employer';

        $interviewDate =
            $interview->interview_date
                ? $interview->interview_date->format('F d, Y')
                : 'Not specified';

        $interviewTime =
            $interview->interview_time
                ? \Carbon\Carbon::parse(
                    $interview->interview_time
                )->format('h:i A')
                : 'Not specified';

        $meetingLink =
            $interview->meeting_link;

        return $this
            ->subject(
                'Your Job Application Has Been Accepted - Interview Scheduled'
            )
            ->html('
                <!DOCTYPE html>
                <html>
                <head>
                    <meta charset="UTF-8">
                    <title>Job Application Accepted</title>
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
                        background:#2563eb;
                        color:#ffffff;
                        text-align:center;
                    "
                >

                    <h1 style="margin:0;">
                        Application Accepted
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
                        Congratulations! Your application for
                        <strong>' . e($jobTitle) . '</strong>
                        has been accepted by
                        <strong>' . e($companyName) . '</strong>.
                    </p>

                    <div
                        style="
                            background:#f8fafc;
                            border-radius:15px;
                            padding:20px;
                            margin:25px 0;
                        "
                    >

                        <p>
                            <strong>Interview Date:</strong>
                            ' . e($interviewDate) . '
                        </p>

                        <p>
                            <strong>Interview Time:</strong>
                            ' . e($interviewTime) . '
                        </p>

                    </div>

                    <div style="text-align:center;">

                        <a
                            href="' . e($meetingLink) . '"
                            style="
                                display:inline-block;
                                background:#2563eb;
                                color:#ffffff;
                                text-decoration:none;
                                padding:14px 25px;
                                border-radius:10px;
                                font-weight:bold;
                            "
                        >
                            Join Interview
                        </a>

                    </div>

                    <p style="margin-top:30px;">
                        Please keep this email for your interview details.
                    </p>

                    <p>
                        Good luck!
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