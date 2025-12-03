<?php

use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;

class Handler extends ExceptionHandler
{
public function render($request, Throwable $exception)
{
    // Handle DB connection issues or missing tables
    if ($exception instanceof QueryException) {
        return response()->json([
            'message' => 'Server is down, please try again later'
        ], 500);
    }

    return parent::render($request, $exception);
}

}