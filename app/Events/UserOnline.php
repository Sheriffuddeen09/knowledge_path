<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

// app/Events/UserOnline.php
class UserOnline implements ShouldBroadcast
{
    public function __construct(public int $userId) {}

    public function broadcastOn()
    {
        return new PresenceChannel('online-users');
    }
}
