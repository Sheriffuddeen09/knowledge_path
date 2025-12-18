<?php

use Illuminate\Support\Facades\Cache;

if (!function_exists('isUserOnline')) {
    function isUserOnline($userId) {
        return Cache::has('user-online-' . $userId);
    }
}
