<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Auth;

class LastSeenMiddleware
{
    public function handle($request, Closure $next)
    {
        if (Auth::check()) {
            Auth::user()->forceFill([
                'last_seen_at' => now(),
            ])->save();
        }

        return $next($request);
    }
}
