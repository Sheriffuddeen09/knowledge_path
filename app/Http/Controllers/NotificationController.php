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
            $names = collect();
            $action = '';

            switch ($n->type) {

                case 'mention':
                    $names = collect([$data['mentioned_by'] ?? null])->filter()->values();
                    $action = "mentioned you in a comment";
                    $message = $names->first() ? "{$names->first()} {$action}" : "You were mentioned in a comment";
                    break;

                case 'friend_suggestion':
                    $names = collect([$data['name'] ?? null])->filter()->values();
                    $message = $names->first() ? "New friend suggestion: {$names->first()}" : "New friend suggestion available";
                    $action = "is your new friend suggestion";
                    break;

                case 'teacher_suggestion':
                    $names = collect([$data['teacher_name'] ?? null])->filter()->values();
                    $message = $names->first() ? "New teacher available: {$names->first()}" : "New teacher available";
                    $action = "is your new teacher suggestion";
                    break;

                case 'post_reaction':
                    $reactors = collect($data['reactors'] ?? []);
                    $names = $reactors->values();
                    $count = $reactors->count();
                    if ($count === 0) {
                        $message = "Someone reacted to your post";
                    } elseif ($count === 1) {
                        $message = "{$reactors[0]} reacted to your post";
                    } elseif ($count === 2) {
                        $message = "{$reactors[0]} and {$reactors[1]} reacted to your post";
                    } else {
                        $message = "{$reactors[0]} and " . ($count - 1) . " others reacted to your post";
                    }
                    $action = "react to your";
                    break;

                case 'post_comment':
                    $commenters = collect($data['commenters'] ?? []);
                    $names = $commenters->values();
                    $isReply = !empty($n->parent_id);
                    $actionText = $isReply ? 'replied to your comment' : 'commented on your post';
                    $count = $commenters->count();

                    if ($count === 0) {
                        $message = "Someone {$actionText}";
                    } elseif ($count === 1) {
                        $message = "{$commenters[0]} {$actionText}";
                    } elseif ($count === 2) {
                        $message = "{$commenters[0]} and {$commenters[1]} {$actionText}";
                    } else {
                        $message = "{$commenters[0]} and " . ($count - 1) . " others {$actionText}";
                    }

                    $action = $actionText;
                    break;

                case 'comment_reaction_comment':
                case 'comment_reaction_reply':
                    $reactors = collect($data['reactors'] ?? []);
                    $names = $reactors->values();
                    $count = $reactors->count();
                    $typeText = $n->type === 'comment_reaction_reply' ? 'reply' : 'comment';
                    $emojiText = $data['emoji'] ?? '';
                    if ($count === 0) {
                        $message = "Someone reacted to your {$typeText} {$emojiText}";
                    } elseif ($count === 1) {
                        $message = "{$reactors[0]} reacted to your {$typeText} {$emojiText}";
                    } elseif ($count === 2) {
                        $message = "{$reactors[0]} and {$reactors[1]} reacted to your {$typeText} {$emojiText}";
                    } else {
                        $message = "{$reactors[0]} and " . ($count - 1) . " others reacted to your {$typeText} {$emojiText}";
                    }
                    $action = "react to your {$typeText}";
                    break;

                case 'post_repost':
                    $reposters = collect($data['reposters'] ?? []);
                    $names = $reposters->values();
                    $count = $reposters->count();
                    if ($count === 0) {
                        $message = "Someone reposted your post";
                    } elseif ($count === 1) {
                        $message = "Your post was reposted by {$reposters[0]}";
                    } elseif ($count === 2) {
                        $message = "Your post was reposted by {$reposters[0]} and {$reposters[1]}";
                    } else {
                        $message = "Your post was reposted by {$reposters[0]} and " . ($count - 1) . " others";
                    }
                    $action = "Repost your post";
                    break;

                case 'chat_blocked':
                    $names = collect([$data['full_name'] ?? ($data['first_name'] . ' ' . $data['last_name'] ?? null)])->filter()->values();
                    $message = $names->first() ? "{$names->first()} blocked you" : "Someone blocked you";
                    $action = "blocked you";
                    break;

                case 'chat_unblocked':
                    $names = collect([$data['full_name'] ?? ($data['first_name'] . ' ' . $data['last_name'] ?? null)])->filter()->values();
                    $message = $names->first() ? "{$names->first()} unblocked you" : "Someone unblocked you";
                    $action = "unblocked you";
                    break;

                case 'chat_reported':
                    $reporterId = $data['reporter_id'] ?? null;
                    $reporter = $reporterId ? \App\Models\User::find($reporterId) : null;
                    $names = collect([$reporter ? trim($reporter->first_name . ' ' . $reporter->last_name) : null])->filter()->values();
                    $action = "reported you in chat";
                    $message = $names->first() ? "{$names->first()} reported you in chat" : "Someone reported you in chat";
                    break;

                case 'post_reported':
                    $reporterId = $data['reporter_id'] ?? null;
                    $reporter = $reporterId ? \App\Models\User::find($reporterId) : null;
                    $names = collect([$reporter ? trim($reporter->first_name . ' ' . $reporter->last_name) : null])->filter()->values();
                    $action = "reported your post";
                    $message = $names->first() ? "{$names->first()} reported your post" : "Someone reported your post";
                    break;

                case 'comment_reported':
                    $reporterId = $data['reporter_id'] ?? null;
                    $reporter = $reporterId ? \App\Models\User::find($reporterId) : null;
                    $names = collect([$reporter ? trim($reporter->first_name . ' ' . $reporter->last_name) : null])->filter()->values();
                    $typeText = !empty($data['parent_id']) ? 'reply' : 'comment';
                    $action = "reported your {$typeText}";
                    $message = $names->first() ? "{$names->first()} reported your {$typeText}" : "Someone reported your {$typeText}";
                    break;

                default:
                    $message = "You have a new notification";
                    $names = collect();
                    $action = "";
                    break;
            }

            return [
                'id' => $n->id,
                'type' => $n->type,
                'names' => $names ?? [],
                'action' => $action ?? '',
                'message' => $message ?? '',
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
