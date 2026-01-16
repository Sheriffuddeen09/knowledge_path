<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\AdminFriendRequest;
use App\Models\Message;

class AdminNotificationController extends Controller
{
    public function requestCount(Request $request)
    {
        $user = $request->user();

        if ($user->role === 'admin') {
            $count = AdminFriendRequest::where('admin_id', $user->id)
                ->where('status', 'pending')
                ->count();
        } else {
            $count = AdminFriendRequest::where('user_id', $user->id)
                ->where('status', 'pending')
                ->count();
        }

        return response()->json([
            'pending_requests' => $count
        ]);
    }


}
