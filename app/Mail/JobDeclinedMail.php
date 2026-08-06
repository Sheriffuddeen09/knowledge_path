<?php

namespace App\Mail;

use App\Models\JobPost;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class JobDeclinedMail extends Mailable
{
    use Queueable, SerializesModels;

    public JobPost $job;

    public function __construct(JobPost $job)
    {
        $this->job = $job;
    }


    public function build()
    {
        return $this
            ->subject('Your Job Post Was Not Approved')
            ->html('
                <!DOCTYPE html>
                <html>

                <head>
                    <meta charset="UTF-8">
                </head>

                <body style="margin:0;padding:40px;background:#f4f7fb;font-family:Arial,sans-serif;">

                <table width="100%">
                    <tr>
                        <td align="center">

                        <table width="700" style="background:#fff;border-radius:12px;padding:40px;">

                            <tr>
                                <td style="text-align:center;">
                                    <h1 style="color:#dc2626;">
                                        Job Not Approved
                                    </h1>
                                </td>
                            </tr>


                            <tr>
                                <td>

                                    <p>
                                        Hello '.$this->job->user->name.',
                                    </p>


                                    <p>
                                        Thank you for submitting your job posting.
                                        After review, we couldn\'t approve it at this time.
                                    </p>


                                    <p>
                                        You may edit the job and submit it again.
                                    </p>


                                    <table width="100%" cellpadding="12">

                                        <tr>
                                            <td>
                                                <strong>Job</strong>
                                            </td>

                                            <td>
                                                '.$this->job->title.'
                                            </td>
                                        </tr>


                                        <tr>
                                            <td>
                                                <strong>Status</strong>
                                            </td>

                                            <td>
                                                Declined
                                            </td>
                                        </tr>

                                    </table>


                                    <p style="margin-top:30px;">
                                        Thank you for choosing Knowledge Path.
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