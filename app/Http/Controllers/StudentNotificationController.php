<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\StudentFriendRequest;
use App\Models\Message;

class StudentNotificationController extends Controller
{
    public function requestCount(Request $request)
    {
        $user = $request->user();

        if ($user->role === 'student') {
            $count = LiveClassRequest::where('student_id', $user->id)
                ->where('status', 'pending')
                ->count();
        } else {
            $count = LiveClassRequest::where('user_id', $user->id)
                ->where('status', 'pending')
                ->count();
        }

        return response()->json([
            'pending_requests' => $count
        ]);
    }


}
