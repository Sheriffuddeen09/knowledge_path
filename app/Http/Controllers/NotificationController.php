<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\LiveClassRequest;
use App\Models\AdminFriendRequest;
use App\Models\StudentFriendRequest;
use App\Models\Message;
use App\Models\Post;

class NotificationController extends Controller
{
    public function requestCount(Request $request)
    {
        $user = $request->user();

        if ($user->role === 'teacher') {
            $count = LiveClassRequest::where('teacher_id', $user->id)
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

    // 🔴 Unread chat messages badge
    public function messageCount(Request $request)
{
    $userId = $request->user()->id;

    $count = Message::whereNull('seen_at')
        ->where('sender_id', '!=', $userId)
        ->whereHas('chat.users', function ($q) use ($userId) {
            $q->where('users.id', $userId);
        })
        ->count();

    return response()->json(['unread_messages' => $count]);
}

public function markAsRead($chatId, Request $request)
{
    Message::where('chat_id', $chatId)
        ->whereNull('seen_at')
        ->where('sender_id', '!=', $request->user()->id)
        ->update(['seen_at' => now()]);

    return response()->json(['status' => true]);
}


    public function friendRequestCount(Request $request)
    {
        $user = auth()->user();

        if ($user->role === 'admin') {

            $count = AdminFriendRequest::where('admin_id', $user->id)
                ->where('status', 'pending')
                ->where('is_seen', 0)
                ->count();

        } else {

            $count = StudentFriendRequest::where('student_id', $user->id)
                ->where('status', 'pending')
                ->where('is_seen', 0)
                ->count();
        }

        return response()->json([
            'count' => $count
        ]);
    }

    public function clearFriendRequests(Request $request)
    {
        $user = auth()->user();

        if ($user->role === 'admin') {

            AdminFriendRequest::where('admin_id', $user->id)
                ->where('status', 'pending')
                ->update(['is_seen' => 1]);

        } else {

            StudentFriendRequest::where('student_id', $user->id)
                ->where('status', 'pending')
                ->update(['is_seen' => 1]);
        }

        return response()->json(['success' => true]);
    }


    public function postCount()
        {
            $homeCount = Post::where('is_new_home', 1)->count();

            $videoCount = Post::where('is_new_video', 1)->count();

            return response()->json([
                'home_count' => $homeCount,
                'video_count' => $videoCount
            ]);
        }


public function clearHomePosts()
{
    Post::where('is_new_home', 1)
        ->update(['is_new_home' => 0]);

    return response()->json(['success' => true]);
}

public function clearVideoPosts()
{
    Post::where('is_new_video', 1)
        ->update(['is_new_video' => 0]);

    return response()->json(['success' => true]);
}

}
