<?php

namespace App\Listeners;

use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\Cache;

class TrackUserOnline
{
    public function handle($event)
    {
        Cache::put(
            'user-online-' . $event->user->id,
            true,
            now()->addMinutes(2)
        );
    }
}
