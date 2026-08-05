<?php

namespace App\Mail;

use App\Models\Community;
use App\Models\CommunityMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class CommunityMessageNotification extends Mailable
{
    use Queueable, SerializesModels;


    public CommunityMessage $messageData;
    public Community $community;


    public function __construct(
        CommunityMessage $message,
        Community $community
    )
    {
        $this->messageData = $message;
        $this->community = $community;
    }



    public function build()
    {

        $sender = $this->messageData->sender;

        $typeMessage = match($this->messageData->type){

            'text' =>
            'A text message was posted.',

            'image' =>
            'An image was posted.',

            'video' =>
            'A video was posted.',

            'voice' =>
            'A voice message was posted.',

            'audio' =>
            'An audio file was posted.',

            'file' =>
            'A file was shared.',

            default =>
            'A new message was posted.'

        };


        return $this
            ->subject(
                'New message in '.$this->community->community_name
            )
            ->from(
                'odukoyasheriff@gmail.com',
                'Islam Path'
            )
            ->html('

            <!DOCTYPE html>
            <html>

            <body style="font-family:Arial,sans-serif;">

            <h2>
                New Community Message
            </h2>


            <p>
                A new message has been posted in:
            </p>


            <h3>
                '.$this->community->community_name.'
            </h3>



            <p>
                <strong>
                    '.$sender->first_name.'
                    '.$sender->last_name.'
                </strong>

                posted a new message.
            </p>



            <p>
                '.$typeMessage.'
            </p>



            <br>


            <p>
                Please login to view the message.
            </p>


            <a href="http://localhost:3000/community"
                style="
                display:inline-block;
                padding:12px 24px;
                background:#2563eb;
                color:white;
                text-decoration:none;
                border-radius:6px;
                "
            >
                Open Chat Channel
            </a>


            </body>

            </html>

            ');
    }
}