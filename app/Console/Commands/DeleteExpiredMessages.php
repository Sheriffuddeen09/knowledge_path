<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Message;

class DeleteExpiredMessages extends Command
{
    protected $signature = 'messages:delete-expired';

    protected $description = 'Delete expired disappearing messages';

    public function handle()
    {
        $deleted = Message::whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->delete();

        $this->info("Deleted {$deleted} messages");

        return 0;
    }
}