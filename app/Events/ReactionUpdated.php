<?php
namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ReactionUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $videoId;
    public $counts;
    public $users;

    public function __construct($videoId, $counts, $users)
    {
        $this->videoId = $videoId;
        $this->counts = $counts;
        $this->users = $users;
    }

    public function broadcastOn()
    {
        return new Channel("video.{$this->videoId}");
    }

    public function broadcastWith()
    {
        return [
            'video_id' => $this->videoId,
            'counts' => $this->counts,
            'users' => $this->users,
        ];
    }
}
