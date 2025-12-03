<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function notifications()
    {
        $user = Auth::user();

        $notifications = [];

        // Check if admin user has not selected choice
        if ($user->role === 'admin' && is_null($user->admin_choice)) {
            $notifications[] = [
                'type' => 'warning',
                'message' => 'You have not selected your admin choice yet. Please select it to access full dashboard features.',
                'action_url' => route('admin.choose_choice') // frontend can use this
            ];
        }

        return response()->json([
            'status' => true,
            'notifications' => $notifications
        ]);
    }
}
