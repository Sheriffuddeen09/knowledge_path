<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Message;

class SyncMessageUsers extends Command
{
    protected $signature = 'messages:sync-users';
    protected $description = 'Attach existing messages to users in message_user table';

    public function handle()
    {
        Message::with('chat')->chunk(100, function ($messages) {
            foreach ($messages as $message) {
                $chat = $message->chat;
                if (!$chat) continue;

                $userIds = array_filter([
                    $chat->teacher_id,
                    $chat->student_id,
                ]);

                foreach ($userIds as $userId) {
                    if (!$message->users()->where('user_id', $userId)->exists()) {
                        $message->users()->attach($userId, ['deleted' => false]);
                    }
                }
            }
        });

        $this->info('Messages successfully synced with users.');
    }
}
