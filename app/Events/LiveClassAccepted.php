<?php

// php artisan make:event LiveClassAccepted
namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Queue\SerializesModels;

class LiveClassAccepted implements ShouldBroadcast
{
    use InteractsWithSockets, SerializesModels;

    public $chat;

    public function __construct($chat)
    {
        $this->chat = $chat;
    }

    public function broadcastOn()
        {
            return [
                new PrivateChannel('user.' . $this->chat->student_id),
                new PrivateChannel('user.' . $this->chat->teacher_id),
            ];
        }


    public function broadcastWith()
    {
        return [
            'chat' => $this->chat
        ];
    }
}
