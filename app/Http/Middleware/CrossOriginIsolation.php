<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CrossOriginIsolation
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        return $response
            ->header('Cross-Origin-Opener-Policy', 'same-origin')
            ->header('Cross-Origin-Embedder-Policy', 'require-corp');
    }
}