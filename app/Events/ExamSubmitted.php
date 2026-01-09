<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ExamSubmitted implements ShouldBroadcast
{
    public $examId;
    public $student;

    public function __construct($examId, $student)
    {
        $this->examId = $examId;
        $this->student = $student;
    }

    public function broadcastOn()
    {
        return new Channel("assignment.$this->examId");
    }
}


