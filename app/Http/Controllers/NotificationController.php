<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\LiveClassRequest;
use App\Models\AdminFriendRequest;
use App\Models\StudentFriendRequest;
use App\Models\Message;
use App\Models\Notification;
use App\Models\Post;

class NotificationController extends Controller
{

    public function index()
{
    $user = auth()->user();

    $notifications = Notification::where('user_id', $user->id)
        ->orderBy('created_at', 'desc')
        ->get()
        ->map(function ($n) {

            $data = json_decode($n->data, true) ?? [];

            $message = '';

           switch ($n->type) {

    case 'mention':
        $data = json_decode($n->data, true);
        $message = $data['mentioned_by'] . " mentioned you in a comment";
        $redirect = $data['redirect_url'] ?? '';
        break;

    case 'friend_suggestion':
        $message = "New friend suggestion: " . ($data['name'] ?? '');
        break;

    case 'teacher_suggestion':
        $message = "New teacher available: " . ($data['teacher_name'] ?? '');
        break;

    case 'post_reaction':
        $reactors = collect($data['reactors'] ?? []);
        $count = $reactors->count();

        if ($count === 1) {
            $message = $reactors[0] . " reacted to your post";
        } elseif ($count === 2) {
            $message = $reactors[0] . " and " . $reactors[1] . " reacted to your post";
        } elseif ($count > 2) {
            $message = $reactors[0] . " and " . ($count - 1) . " others reacted to your post";
        }
        break;

   case 'post_comment':

    $commenters = collect($data['commenters'] ?? []);
    $count = $commenters->count();

    $isReply = !empty($n->parent_id);

    $actionText = $isReply
    ? 'replied to your comment'
    : 'commented on your post';

    if ($count === 1) {
        $message = $commenters[0] . " " . $actionText;
    } elseif ($count === 2) {
        $message = $commenters[0] . " and " . $commenters[1] . " " . $actionText;
    } elseif ($count > 2) {
        $message = $commenters[0] . " and " . ($count - 1) . " others " . $actionText;
    }

    break;
    
    case 'comment_reaction_comment':
    $reactors = collect($data['reactors'] ?? []);
    $count = $reactors->count();

    if ($count === 1) {
        $message = $reactors[0] . " reacted to your comment " . ($data['emoji'] ?? '');
    } elseif ($count === 2) {
        $message = $reactors[0] . " and " . $reactors[1] . " reacted to your comment " . ($data['emoji'] ?? '');
    } elseif ($count > 2) {
        $message = $reactors[0] . " and " . ($count - 1) . " others reacted to your comment " . ($data['emoji'] ?? '');
    }
    break;

    case 'comment_reaction_reply':
    $reactors = collect($data['reactors'] ?? []);
    $count = $reactors->count();

    if ($count === 1) {
        $message = $reactors[0] . " reacted to your reply " . ($data['emoji'] ?? '');
    } elseif ($count === 2) {
        $message = $reactors[0] . " and " . $reactors[1] . " reacted to your reply " . ($data['emoji'] ?? '');
    } elseif ($count > 2) {
        $message = $reactors[0] . " and " . ($count - 1) . " others reacted to your reply " . ($data['emoji'] ?? '');
    }
    break;

    case 'chat_blocked':
        $message = ($data['full_name'] ?? ($data['first_name'] . ' ' . $data['last_name'])) . " blocked you";
        break;

    case 'chat_unblocked':
        $message = ($data['full_name'] ?? ($data['first_name'] . ' ' . $data['last_name'])) . " unblocked you";
        break;

    case 'chat_reported':
        $message = $data['reporter_name'] . " reported you in a chat";
        break;

    case 'post_reported':
        $message = $data['reporter_name'] . " reported your post";
        break;

    case 'comment_reported':

            $type = !empty($data['parent_id']) ? 'reply' : 'comment';
            $message = $data['reporter_name'] . " reported your {$type}";

            break;

        break;
}
            return [
                'id' => $n->id,
                'type' => $n->type,
                'names' => $data['reactors'] ?? $data['commenters'] ?? [
                    $data['mentioned_by'] ?? 
                    $data['name'] ?? 
                    $data['teacher_name'] ?? 
                    $data['reporter_name'] ?? 
                    ($data['full_name'] ?? null)
                ],
                'action' => $message, // ✅ USE THE BUILT MESSAGE
                'redirect_url' => $n->redirect_url,
                'read' => $n->read,
                'created_at' => $n->created_at->diffForHumans(),
            ];
        });

    return response()->json($notifications);
}

public function markAsReadNotification($id)
    {
        $notification = Notification::where('id', $id)
            ->where('user_id', auth()->id())
            ->firstOrFail();

        $notification->update(['read' => true]);

        return response()->json(['status' => true]);
    }

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


public function unreadMessageSendersCount()
{
    $userId = auth()->id();

    $count = Message::where('receiver_id', $userId)
        ->whereNull('seen_at')
        ->distinct('sender_id')
        ->count('sender_id');

    return response()->json([
        'message' => $count
    ]);
}


public function clearUnreadMessages()
{
    $userId = auth()->id();

    Message::where('receiver_id', $userId)
        ->whereNull('seen_at')
        ->update([
            'seen_at' => now()
        ]);

    return response()->json(['success' => true]);
}


public function unreadNotificationsCount()
{
    $userId = auth()->id();

    // Count all unread notifications for the authenticated user
    $count = Notification::where('user_id', $userId)
        ->where('read', false)
        ->count();

    return response()->json([
        'notification' => $count, // clear key for frontend
    ]);
}



public function markNotificationsAsRead()
{
    $userId = auth()->id();

    // Mark all notifications as read
    Notification::where('user_id', $userId)
        ->where('read', false)
        ->update([
            'read' => true
        ]);

    return response()->json([
        'success' => true,
        'unread_count' => 0, // immediately reflect zero in frontend markAsReadNotification
    ]);
}

}
