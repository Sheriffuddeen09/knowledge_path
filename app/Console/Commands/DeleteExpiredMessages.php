<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class DeleteExpiredMessages extends Command
{

    protected $signature = 'app:delete-expired-messages';
    protected $description = 'Command description';

   
   public function handle()
    {
        $messages = Message::whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->get();

        foreach ($messages as $msg) {
            event(new MessageDeleted($msg->id, $msg->chat_id));
            $msg->delete();
        }
    }

protected function schedule(Schedule $schedule)
{
    $schedule->command('messages:delete-expired')->everyMinute();
}
}
