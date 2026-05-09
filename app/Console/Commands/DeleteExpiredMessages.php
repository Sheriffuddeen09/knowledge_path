<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class DeleteExpiredMessages extends Command
{

    protected $signature = 'app:delete-expired-messages';
    protected $description = 'Command description';

   
   public function handle()
        {
            Message::whereNotNull('expires_at')
                ->where('expires_at', '<=', now())
                ->delete();

            return 0;
        }

    protected function schedule(Schedule $schedule)
    {
        $schedule->command('messages:delete-expired')->everyMinute();
    }
}
