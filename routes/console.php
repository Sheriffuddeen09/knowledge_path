<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use App\Models\Chat;
use Illuminate\Support\Facades\Schedule;
use App\Models\Message;


Schedule::call(function () {

    Message::whereNotNull('expires_at')
        ->where('expires_at', '<=', now())
        ->delete();

})->everyMinute();


Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');


Broadcast::channel('chat.{chatId}', function ($user, $chatId) {
    return Chat::where('id', $chatId)
        ->where(function ($q) use ($user) {
            $q->where('teacher_id', $user->id)
              ->orWhere('student_id', $user->id);
        })
        ->exists();
});


