<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetCrossOriginHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Use 'same-site' if on the same domain, 'cross-origin' otherwise
        $response->header('Cross-Origin-Resource-Policy', 'cross-origin'); 
        // Optional: for broader compatibility
        $response->header('Access-Control-Allow-Origin', '*'); 

        return $response;
    }
}