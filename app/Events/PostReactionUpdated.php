<?php
namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PostReactionUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $postId;
    public $counts;
    public $users;

    public function __construct($postId, $counts, $users)
    {
        $this->postId = $postId;
        $this->counts = $counts;
        $this->users = $users;
    }

    public function broadcastOn()
    {
        return new Channel("post.{$this->postId}");
    }

    public function broadcastWith()
    {
        return [
            'post_id' => $this->postId,
            'counts' => $this->counts,
            'users' => $this->users,
        ];
    }
}
