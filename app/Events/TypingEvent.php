<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TypingEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $chat_id;
    public $user_id;

    public function __construct($chat_id, $user_id)
    {
        $this->chat_id = $chat_id;
        $this->user_id = $user_id;
    }

    public function broadcastOn()
    {
        return new PrivateChannel('chat.' . $this->chat_id);
    }

    public function broadcastAs()
    {
        return 'typing';
    }
}
