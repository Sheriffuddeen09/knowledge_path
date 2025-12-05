<?php

namespace App\Http\Middleware;

use Closure;

class EnsureTeacherChoice
{
    public function handle($request, Closure $next)
    {
        $user = $request->user();

        // Must be logged in
        if (!$user) {
            return response()->json([
                'message' => 'Unauthorized'
            ], 401);
        }

        // Must have chosen Arabic Teacher
        if ($user->admin_choice !== 'arabic_teacher') {
            return response()->json([
                'message' => 'Access denied – You selected another option.'
            ], 403);
        }

        return $next($request);
    }
}
