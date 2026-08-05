<?php

namespace App\Mail;

use App\Models\Message;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class NewMessageNotification extends Mailable
{
    use Queueable, SerializesModels;

    public Message $messageData;

    public function __construct(Message $message)
    {
        $this->messageData = $message;
    }

   public function build()
{
    $receiver = optional($this->messageData->receiver)->first_name ?? 'User';

    $sender = optional($this->messageData->sender)->first_name . ' ' .
              optional($this->messageData->sender)->last_name;

    $type = $this->messageData->type;

    $messageText = $this->messageData->message ?? '';

    $link = config('app.frontend_url') . '/';


    if ($type === 'voice') {
        $content = "You received a voice message.";
    } elseif ($type === 'image') {
        $content = "You received an image.";
    } elseif ($type === 'video') {
        $content = "You received a video.";
    } elseif ($type === 'audio') {
        $content = "You received an audio file.";
    } elseif ($type === 'file') {
        $content = "You received a file.";
    } elseif ($type === 'text') {
        $content = "You received a text message.<br>
                    <blockquote>$messageText</blockquote>";
    } else {
        $content = "You have received a new message.";
    }


    return $this
        ->subject('You Have A New Message')
        ->html("
            <!DOCTYPE html>
            <html>
            <body style='font-family:Arial,sans-serif;'>

            <h2>You Have Received A New Message</h2>

            <p>
                Hello {$receiver},
            </p>

            <p>
                <strong>{$sender}</strong>
                has sent you a new message.
            </p>

            <p>
                {$content}
            </p>

            <br>

            <a href='{$link}'
            style='
                display:inline-block;
                padding:12px 24px;
                background:#2563eb;
                color:#ffffff;
                text-decoration:none;
                border-radius:6px;
            '>
                Open Messages
            </a>

            </body>
            </html>
        ");
}
}