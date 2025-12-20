<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;


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

    public function index()
    {
        // Get all users except current logged-in user
        $users = User::where('id', '!=', Auth::id())
                     ->select('id', 'first_name', 'last_name', 'email', 'role')
                     ->get();

        return response()->json($users);
    }
}
