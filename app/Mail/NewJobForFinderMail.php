<?php

namespace App\Mail;

use App\Models\JobPost;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class NewJobForFinderMail extends Mailable
{
    use Queueable, SerializesModels;

    public JobPost $job;
    public User $finder;

    /**
     * Create a new message instance.
     */
    public function __construct(JobPost $job, User $finder)
    {
        $this->job = $job;
        $this->finder = $finder;
    }

    /**
     * Build the message.
     */
    public function build()
    {
        $job = $this->job;
        $finder = $this->finder;

        $categoryName = $job->category?->name ?? 'Job';

        $companyName = $job->user
            ? trim(
                $job->user->first_name . ' ' .
                $job->user->last_name
            )
            : 'A company';

        $location = $job->location
            ? e($job->location)
            : null;

        $jobType = $job->job_type
            ? ucfirst(str_replace('-', ' ', $job->job_type))
            : null;

        return $this
            ->subject("New {$categoryName} Job Available")
            ->html('
                <!DOCTYPE html>

                <html>

                <head>
                    <meta charset="UTF-8">

                    <meta
                        name="viewport"
                        content="width=device-width, initial-scale=1.0"
                    >

                    <title>New Job Available</title>
                </head>

                <body
                    style="
                        margin:0;
                        padding:0;
                        background:#f5f7f6;
                        font-family:Arial, Helvetica, sans-serif;
                        color:#222;
                    "
                >

                    <div
                        style="
                            max-width:600px;
                            margin:30px auto;
                            background:#ffffff;
                            border-radius:16px;
                            overflow:hidden;
                            box-shadow:0 4px 20px rgba(0,0,0,0.08);
                        "
                    >

                        <!-- Header -->

                        <div
                            style="
                                background:#16803c;
                                padding:28px 25px;
                                color:#ffffff;
                            "
                        >

                            <h1
                                style="
                                    margin:0;
                                    font-size:22px;
                                "
                            >
                                New Job Opportunity
                            </h1>

                            <p
                                style="
                                    margin:8px 0 0;
                                    font-size:14px;
                                    opacity:.9;
                                "
                            >
                                A new job matching your selected category
                                has been posted.
                            </p>

                        </div>


                        <!-- Content -->

                        <div style="padding:30px 25px;">

                            <p
                                style="
                                    margin-top:0;
                                    font-size:15px;
                                "
                            >
                                Hello ' .
                                e($finder->first_name ?? 'there') .
                                ',
                            </p>


                            <p
                                style="
                                    font-size:14px;
                                    line-height:1.7;
                                    color:#555;
                                "
                            >
                                A new job has been approved and posted
                                in the
                                <strong>' .
                                e($categoryName) .
                                '</strong>
                                category.
                            </p>


                            <!-- Job Card -->

                            <div
                                style="
                                    margin-top:25px;
                                    padding:20px;
                                    background:#f7faf8;
                                    border:1px solid #e3eee7;
                                    border-radius:12px;
                                "
                            >

                                <h2
                                    style="
                                        margin:0 0 12px;
                                        font-size:18px;
                                        color:#16803c;
                                    "
                                >
                                    ' .
                                    e($job->title) .
                                    '
                                </h2>


                                <p
                                    style="
                                        margin:7px 0;
                                        font-size:13px;
                                        color:#555;
                                    "
                                >
                                    <strong>Category:</strong>
                                    ' .
                                    e($categoryName) .
                                    '
                                </p>


                                <p
                                    style="
                                        margin:7px 0;
                                        font-size:13px;
                                        color:#555;
                                    "
                                >
                                    <strong>Posted by:</strong>
                                    ' .
                                    e($companyName) .
                                    '
                                </p>


                                ' .
                                ($location
                                    ? '
                                        <p
                                            style="
                                                margin:7px 0;
                                                font-size:13px;
                                                color:#555;
                                            "
                                        >
                                            <strong>Location:</strong>
                                            ' . $location . '
                                        </p>
                                    '
                                    : '') .
                                '


                                ' .
                                ($jobType
                                    ? '
                                        <p
                                            style="
                                                margin:7px 0;
                                                font-size:13px;
                                                color:#555;
                                            "
                                        >
                                            <strong>Job type:</strong>
                                            ' . e($jobType) . '
                                        </p>
                                    '
                                    : '') .
                                '

                            </div>


                            <!-- Button -->

                            <div
                                style="
                                    text-align:center;
                                    margin-top:28px;
                                "
                            >

                                <a
                                    href="' .
                                    config('app.url') .
                                    '/job-finder"
                                    style="
                                        display:inline-block;
                                        background:#16803c;
                                        color:#ffffff;
                                        text-decoration:none;
                                        padding:13px 24px;
                                        border-radius:9px;
                                        font-size:14px;
                                        font-weight:bold;
                                    "
                                >
                                    View Job Opportunities
                                </a>

                            </div>


                            <p
                                style="
                                    margin-top:30px;
                                    font-size:12px;
                                    line-height:1.6;
                                    color:#888;
                                "
                            >
                                You are receiving this email because
                                you have a job finder profile matching
                                this job category.
                            </p>

                        </div>


                        <!-- Footer -->

                        <div
                            style="
                                padding:18px 25px;
                                background:#f8f9f8;
                                text-align:center;
                                font-size:11px;
                                color:#888;
                            "
                        >
                            Islam Path of Knowledge
                        </div>

                    </div>

                </body>

                </html>
            ');
    }
}