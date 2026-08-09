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

        /*
        |--------------------------------------------------------------------------
        | Load all required relationships
        |--------------------------------------------------------------------------
        */

        $application->load([
            'user',
            'jobPost.user.jobProfile',
        ]);

        /*
        |--------------------------------------------------------------------------
        | Applicant
        |--------------------------------------------------------------------------
        */

        $userName = trim(
            ($application->user->first_name ?? '') . ' ' .
            ($application->user->last_name ?? '')
        );

        if (!$userName) {
            $userName = 'Applicant';
        }

        /*
        |--------------------------------------------------------------------------
        | Job
        |--------------------------------------------------------------------------
        */

        $jobTitle =
            $application->jobPost?->title
            ?? 'Job Position';

        /*
        |--------------------------------------------------------------------------
        | Company
        |--------------------------------------------------------------------------
        */

        $companyName =
            $application
                ->jobPost
                ?->user
                ?->jobProfile
                ?->company_name
            ?? 'the employer';

        /*
        |--------------------------------------------------------------------------
        | Interview date
        |--------------------------------------------------------------------------
        */

        $interviewDate =
            $interview->interview_date
                ? $interview->interview_date->format('F d, Y')
                : 'Not specified';

        /*
        |--------------------------------------------------------------------------
        | Interview time
        |--------------------------------------------------------------------------
        */

        $interviewTime =
            $interview->interview_time
                ? \Carbon\Carbon::parse(
                    $interview->interview_time
                )->format('h:i A')
                : 'Not specified';

        /*
        |--------------------------------------------------------------------------
        | Meeting link
        |--------------------------------------------------------------------------
        */

        $meetingLink =
            $interview->meeting_link;

        /*
        |--------------------------------------------------------------------------
        | Email
        |--------------------------------------------------------------------------
        */

        return $this
            ->subject(
                'Your Job Application Has Been Accepted - Interview Scheduled'
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
                        Job Application Accepted
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
                                        width:100%;
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

                                            <h1
                                                style="
                                                    margin:0;
                                                    font-size:28px;
                                                "
                                            >
                                                Application Accepted
                                            </h1>

                                        </td>

                                    </tr>


                                    <!-- BODY -->

                                    <tr>

                                        <td
                                            style="
                                                padding:35px;
                                                color:#334155;
                                            "
                                        >

                                            <p
                                                style="
                                                    font-size:16px;
                                                    line-height:1.6;
                                                "
                                            >

                                                Hello

                                                <strong>
                                                    ' . e($userName) . '
                                                </strong>,

                                            </p>


                                            <p
                                                style="
                                                    font-size:16px;
                                                    line-height:1.7;
                                                "
                                            >

                                                Congratulations!

                                                Your application for

                                                <strong>
                                                    ' . e($jobTitle) . '
                                                </strong>

                                                has been accepted by

                                                <strong>
                                                    ' . e($companyName) . '
                                                </strong>.

                                            </p>


                                            <!-- INTERVIEW DETAILS -->

                                            <div
                                                style="
                                                    background:#f8fafc;
                                                    border-radius:15px;
                                                    padding:20px;
                                                    margin:25px 0;
                                                    border:1px solid #e2e8f0;
                                                "
                                            >

                                                <p
                                                    style="
                                                        margin:0 0 12px 0;
                                                    "
                                                >

                                                    <strong>
                                                        Interview Date:
                                                    </strong>

                                                    ' . e($interviewDate) . '

                                                </p>


                                                <p
                                                    style="
                                                        margin:0;
                                                    "
                                                >

                                                    <strong>
                                                        Interview Time:
                                                    </strong>

                                                    ' . e($interviewTime) . '

                                                </p>

                                            </div>


                                            <!-- JOIN BUTTON -->

                                            <div
                                                style="
                                                    text-align:center;
                                                    margin:30px 0;
                                                "
                                            >

                                                <a
                                                    href="' . e($meetingLink) . '"
                                                    target="_blank"
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


                                            <p
                                                style="
                                                    margin-top:30px;
                                                    font-size:15px;
                                                    line-height:1.6;
                                                "
                                            >

                                                Please keep this email for your
                                                interview details.

                                            </p>


                                            <p
                                                style="
                                                    font-size:15px;
                                                "
                                            >

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