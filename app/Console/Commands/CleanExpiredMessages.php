<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Message;
use App\Events\MessageDeleted;

class CleanExpiredMessages extends Command
{
    protected $signature = 'messages:clean-expired';
    protected $description = 'Delete expired disappearing messages';

    public function handle()
    {
        // 1. Get all messages that are expired
        $expiredMessages = Message::whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->get();

        // 2. Fire an event for each expired message (for real-time removal in frontend)
        $expiredMessages->each(function ($msg) {
            event(new MessageDeleted($msg->id, $msg->chat_id));
        });

        // 3. Delete expired messages from database
        Message::whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->delete();

        $this->info('Expired messages cleaned up.');
    }
}