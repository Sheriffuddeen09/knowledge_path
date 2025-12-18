<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class UserController extends Controller
{
    public function onlineStatus(User $user): JsonResponse
    {
        return response()->json([
            'online' => $user->last_seen_at
                ? $user->last_seen_at->diffInSeconds(now()) < 60
                : false
        ]);
    }

    public function onlineStatusBulk(Request $request): JsonResponse
    {
        $userIds = array_unique($request->input('user_ids', []));

        $statuses = User::whereIn('id', $userIds)->get()
            ->mapWithKeys(fn ($user) => [
                $user->id => $user->last_seen_at
                    ? $user->last_seen_at->diffInSeconds(now()) < 60
                    : false
            ]);

        return response()->json($statuses);
    }
}
