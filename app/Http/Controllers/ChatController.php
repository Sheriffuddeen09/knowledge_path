<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Chat;
use App\Models\Message;
use App\Events\NewMessage;
use Illuminate\Support\Facades\Storage;
use App\Events\TypingEvent;
use App\Models\User;
use App\Models\Block;
use App\Models\MessageReport;
use App\Models\MessageReaction;
use App\Events\MessageSent;

   class ChatController extends Controller
{

    
    // Chat list (left panel)

    public function myChats()
{
    $userId = auth()->id();

    $chats = Chat::where('teacher_id', $userId)
        ->orWhere('student_id', $userId)
        ->with([
            'teacher',
            'student',
            'blocks',
            'messages' => function ($q) {
                $q->latest()->limit(1);
            }
        ])
        ->withCount(['messages as unread_count' => function ($q) use ($userId) {
            $q->where('sender_id', '!=', $userId)
              ->whereNull('seen_at');
        }])
        ->latest('updated_at')
        ->get();

    $chats->each(function ($chat) use ($userId) {

        // Latest message
        $chat->latest_message = $chat->messages->first() ?? null;
        unset($chat->messages);

        // Other user
        $other = $chat->teacher_id === $userId ? $chat->student : $chat->teacher;

        // Online status
        $chat->other_online = $other && $other->last_seen
            ? \Carbon\Carbon::parse($other->last_seen)->diffInSeconds(now()) <= 60
            : false;

        // 🔒 BLOCK INFO
        $block = $chat->blocks->first();

        $chat->block_info = $block ? [
            'blocked'      => true,
            'blocker_id'   => $block->blocker_id,
            'blocked_id'   => $block->blocked_id,
            'blocked_by_me'=> $block->blocker_id === $userId,
        ] : null;

        $chat->is_blocked_for_me = $block
            ? in_array($userId, [$block->blocker_id, $block->blocked_id])
            : false;
    });

    return response()->json($chats);
}



// get message function
//   public function messages(Chat $chat)
// {
//     $userId = auth()->id();

//     // 🔒 BLOCK CHECK
//     if ($chat->isBlockedFor($userId)) {
//         return response()->json([
//             'message' => 'This chat is blocked'
//         ], 403);
//     }

//     // Mark delivered
//     $chat->messages()
//         ->whereNull('delivered_at')
//         ->where('sender_id', '!=', $userId)
//         ->update(['delivered_at' => now()]);

//     return $chat->messages()
//         ->with([
//             'sender:id,first_name,last_name,role',
//             'reactions.user:id,first_name,last_name'
//         ])
//         ->orderBy('created_at')
//         ->get();
// }


public function messages(Chat $chat)
{
    $userId = auth()->id();

    // 🔒 BLOCK CHECK
    if ($chat->isBlockedFor($userId)) {
        return response()->json([
            'message' => 'This chat is blocked'
        ], 403);
    }

    // ✅ Mark messages as DELIVERED (receiver opened chat)
    $chat->messages()
        ->whereNull('delivered_at')
        ->where('sender_id', '!=', $userId)
        ->update(['delivered_at' => now()]);

    // ✅ Mark messages as READ (receiver viewed them)
    $chat->messages()
        ->where('is_read', false)
        ->where('sender_id', '!=', $userId)
        ->update(['is_read' => true]);

    return $chat->messages()
        ->with([
            'sender:id,first_name,last_name,role',
            'reactions.user:id,first_name,last_name'
        ])
        ->orderBy('created_at')
        ->get();
}


public function index(Request $request)
{
    $userId = $request->user()->id;

    $chats = Chat::where('teacher_id', $userId)
        ->orWhere('student_id', $userId)
        ->with([
            'teacher',
            'student',
            'latestMessage.sender',
            'messages' => function ($q) {
                $q->latest();
            }
        ])
        ->get()
        ->map(function ($chat) use ($userId) {

            // Unread count
            $chat->unread_count = $chat->messages
                ->whereNull('seen_at')
                ->where('sender_id', '!=', $userId)
                ->count();

            // Block info
            $block = $chat->blocks()->first();
            $chat->block_info = $block ? [
                'blocked' => true,
                'blocker_id' => $block->blocker_id,
                'blocked_id' => $block->blocked_id,
            ] : null;

            return $chat;
        });

    return response()->json($chats);
}




    // Send message 
   public function send(Request $request)
{
    $request->validate([
        'chat_id'    => 'required|exists:chats,id',
        'type' => 'required|in:text,image,voice,video,file',
        'message'    => 'nullable|string',
        'file'       => 'nullable|file|max:20480',
        'replied_to' => 'nullable|exists:messages,id',
    ]);

    $chat = Chat::findOrFail($request->chat_id);

    // 🔒 BLOCK CHECK (THIS IS THE KEY)
    if ($chat->isBlockedFor(auth()->id())) {
        return response()->json([
            'message' => 'You are blocked in this chat'
        ], 403);
    }

    $path = null;
    $fileName = null;

    if ($request->hasFile('file')) {
        $file = $request->file('file');
        $path = $file->store('chat_files', 'public');
        $fileName = $file->getClientOriginalName();
    }

    $message = Message::create([
        'chat_id'    => $chat->id,
        'sender_id'  => auth()->id(),
        'type'       => $request->type,
        'message'    => $request->message,
        'file'       => $path,
        'file_name'  => $fileName,
        'replied_to' => $request->replied_to,
        'is_read' => false
    ]);

    // Attach message to both participants

    \Log::info('FILE DEBUG', [
    'hasFile' => $request->hasFile('file'),
    'file' => $request->file('file'),
    'all' => $request->all(),
]);

    $userIds = collect([$chat->teacher_id, $chat->student_id])->filter();

    foreach ($userIds as $userId) {
        $message->users()->attach($userId, ['deleted' => false]);
    }

    $message->load(['sender', 'repliedMessage.sender']);

    broadcast(new NewMessage($message))->toOthers();

    return response()->json($message);
}


// sendvoice note function
   public function sendVoice(Request $request)
{
    $request->validate([
        'chat_id' => 'required|exists:chats,id',
        'voice'   => 'required|file|mimes:webm,mpeg,wav,ogg',
    ]);

    $chat = Chat::findOrFail($request->chat_id);

    // 🔒 BLOCK CHECK
    if ($chat->isBlockedFor(auth()->id())) {
        return response()->json([
            'message' => 'You are blocked in this chat'
        ], 403);
    }

    $file = $request->file('voice');
    $path = $file->store('chat_files', 'public');

    $message = Message::create([
        'chat_id'   => $chat->id,
        'sender_id' => auth()->id(),
        'type'      => 'voice',
        'file'      => $path,
        'is_read' => false
    ]);

    $message->load('sender');

    broadcast(new NewMessage($message))->toOthers();

    return response()->json([
        'message' => $message,
    ]);
}


// unread Senders Count


 public function unreadSendersCount()
    {
        $userId = auth()->id();

        $count = \App\Models\Message::where('is_read', false)
            ->where('sender_id', '!=', $userId)
            ->whereHas('chat', function ($q) use ($userId) {
                $q->where('teacher_id', $userId)
                  ->orWhere('student_id', $userId);
            })
            ->distinct('sender_id')
            ->count('sender_id');

        return response()->json([
            'unread_senders' => $count
        ]);
    }




// markSeen function


public function markChatSeen($chatId)
{
    Message::where('chat_id', $chatId)
        ->where('receiver_id', auth()->id())
        ->whereNull('seen_at')
        ->update([
            'seen_at' => now(),
            'is_read' => true
        ]);

    return response()->noContent();
}



// Typing Function

public function typing(Request $request)
{
    broadcast(new TypingEvent(
        $request->chat_id,
        auth()->id()
    ))->toOthers();

    return response()->noContent();
}




// delete function

public function destroy(Message $message)
{
    $userId = auth()->id();

    // Sender → delete for everyone
    if ($message->sender_id === $userId) {
        $message->delete();
        return response()->json([
            'message' => 'Message deleted for everyone'
        ]);
    }

    // Other user → delete only for me
    $message->users()
        ->updateExistingPivot($userId, ['deleted' => true]);

    return response()->json([
        'message' => 'Message deleted for you'
    ]);
}


// Clear all message function


public function clearChat(Chat $chat)
{
    $userId = auth()->id();

    // Make sure user belongs to this chat
    abort_if(
        $chat->teacher_id !== $userId && $chat->student_id !== $userId,
        403,
        'Unauthorized'
    );

    // Get all message IDs in this chat
    $messageIds = Message::where('chat_id', $chat->id)->pluck('id');

    // Mark messages as deleted for this user only
    \DB::table('message_user')
        ->whereIn('message_id', $messageIds)
        ->where('user_id', $userId)
        ->update([
            'deleted' => true,
            'updated_at' => now(),
        ]);

    return response()->json([
        'message' => 'Chat cleared successfully.',
    ]);
}


// Edit function

public function edit (Request $request, Message $message)
{
    abort_if($message->sender_id !== auth()->id(), 403);
    abort_if($message->type !== 'text', 403);
    abort_if($message->seen_at, 403);

    $request->validate([
        'message' => 'required|string'
    ]);

    $message->update([
        'message' => $request->message,
        'edited' => true,
    ]);

    return response()->json([
        'message' => $message->fresh()
    ]);
}



//  forward function

public function forwardMultiple(Request $request)
{
    $request->validate([
        'receiver_ids' => 'required|array|min:1',
        'message_ids' => 'required|array|min:1',
    ]);

    $sender = auth()->user();
    $messageIds = $request->message_ids;
    $receiverIds = $request->receiver_ids;

    $forwardedChats = [];

    foreach ($receiverIds as $receiverId) {
        $receiver = User::findOrFail($receiverId);

        // Find or create chat
        $chat = Chat::where(function ($q) use ($sender, $receiver) {
            $q->where('teacher_id', $sender->id)->where('student_id', $receiver->id);
        })->orWhere(function ($q) use ($sender, $receiver) {
            $q->where('teacher_id', $receiver->id)->where('student_id', $sender->id);
        })->first();

        if (!$chat) {
            $chat = Chat::create([
                'teacher_id' => $sender->role === 'teacher' ? $sender->id : $receiver->id,
                'student_id' => $sender->role === 'student' ? $sender->id : $receiver->id,
            ]);
        }

        // Forward messages
        foreach ($messageIds as $id) {
            $originalMessage = Message::findOrFail($id);

            Message::create([
                'chat_id' => $chat->id,
                'sender_id' => $sender->id,
                'type' => $originalMessage->type,
                'message' => $originalMessage->message,
                'file' => $originalMessage->file,
                'forwarded_from' => $originalMessage->sender_id,
            ]);
        }

        // Load chat with participants and latest messages
        $chat = Chat::with(['teacher', 'student', 'messages' => function ($q) {
            $q->latest()->limit(50);
        }])->find($chat->id);

        $forwardedChats[] = $chat;
    }

    return response()->json([
        'success' => true,
        'chats' => $forwardedChats, // return full chat objects
    ]);
}



// React Function

public function toggle(Request $request)
{
    $request->validate([
        'message_id' => 'required|exists:messages,id',
        'emoji' => 'required|string',
    ]);

    $reaction = MessageReaction::where([
        'message_id' => $request->message_id,
        'user_id' => auth()->id(),
        'emoji' => $request->emoji,
    ])->first();

    if ($reaction) {
        $reaction->delete(); // remove reaction
    } else {
        MessageReaction::create([
            'message_id' => $request->message_id,
            'user_id' => auth()->id(),
            'emoji' => $request->emoji,
        ]);
    }

    return Message::with(['reactions.user'])->find($request->message_id);
}


}