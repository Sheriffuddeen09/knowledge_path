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
        ->with(['teacher', 'student'])
        ->with(['messages' => function ($q) use ($userId) {
            $q->latest()->limit(1);
        }])
        ->withCount(['messages as unread_count' => function ($q) use ($userId) {
            $q->where('sender_id', '!=', $userId)
              ->whereNull('seen_at');
        }])
        ->latest('updated_at')
        ->get();

    $chats->each(function ($chat) use ($userId) {
        $chat->latest_message = $chat->messages->first() ?? null;
        unset($chat->messages);

        // Determine the "other user"
        $other = $chat->teacher_id === $userId ? $chat->student : $chat->teacher;

        // Simple online check: if last_seen within last 60 seconds, online
        $chat->other_online = $other && $other->last_seen 
            ? \Carbon\Carbon::parse($other->last_seen)->diffInSeconds(now()) <= 60
            : false;
    });

    return $chats;
}



// get message function
  public function messages(Chat $chat)
{
    $userId = auth()->id();

    // Mark messages as delivered
    $chat->messages()
        ->whereNull('delivered_at')
        ->where('sender_id', '!=', $userId)
        ->update(['delivered_at' => now()]);

    // ✅ LOAD REACTIONS + USERS
    return $chat->messages()
        ->with([
            'sender:id,first_name,last_name,role',
            'reactions.user:id,first_name,last_name' // 👈 THIS IS THE KEY
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
            'messages' => function ($q) {
                $q->latest();
            }
        ])
        ->get()
        ->map(function ($chat) use ($userId) {
            $chat->unread_count = $chat->messages
                ->whereNull('seen_at')
                ->where('sender_id', '!=', $userId)
                ->count();

            return $chat;
        });

    return response()->json($chats);
}



    // Send message
   public function send(Request $request)
{
    $request->validate([
        'chat_id' => 'required|exists:chats,id',
        'type' => 'required|string',
        'message' => 'nullable|string',
        'file' => 'nullable|file|max:20480', // 20MB
        'replied_to' => 'nullable|exists:messages,id',

    ]);

    $path = null;
    $fileName = null;

    if ($request->hasFile('file')) {
        $file = $request->file('file');
        $path = $file->store('chat_files', 'public');
        $fileName = $file->getClientOriginalName();
    }

    $message = Message::create([
        'chat_id' => $request->chat_id,
        'sender_id' => auth()->id(),
        'type' => $request->type,     // text, image, video, audio, file
        'message' => $request->message,
        'file' => $path,
        'file_name' => $fileName,
        'replied_to' => $request->replied_to,
    ]);

    // Attach message to all chat participants in message_user table
    $chat = Chat::with(['teacher', 'student'])->findOrFail($request->chat_id);

    $userIds = collect([
        $chat->teacher_id,
        $chat->student_id,
    ])->filter();

    foreach ($userIds as $userId) {
        $message->users()->attach($userId, ['deleted' => false]);
    }

    $message->load('sender');

    broadcast(new NewMessage($message))->toOthers();


    return Message::with([
        'sender',
        'repliedMessage.sender'
    ])->find($message->id);
}

   

// sendvoice note function
    public function sendVoice(Request $request)
{
    $request->validate([
                'chat_id' => 'required|exists:chats,id',
                'voice' => 'required|file|mimes:webm,mpeg,wav,ogg',
            ]);

    $file = $request->file('voice');

    // Save the file to storage
    $path = $file->store('chat_files', 'public'); // this sets $path

    // Save message in database
    $message = Message::create([
        'chat_id'   => $request->chat_id,
        'sender_id' => auth()->id(),
        'type'      => 'voice', // must match enum
        'file'      => $path,
    ]);

    $message->load('sender');

    return response()->json([
        'message' => $message,
    ]);
}


// markSeen function

public function markSeen(Message $message)
{
    abort_if($message->sender_id === auth()->id(), 403);

    $message->update(['seen_at' => now()]);

    broadcast(new MessageSeen($message))->toOthers();

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



public function toggleBlock(Request $request)
{
  Block::firstOrCreate([
    'user_id' => auth()->id(),
    'blocked_user_id' => $request->user_id,
  ]);

  return response()->json(['blocked' => true]);
}


public function isBlocked($userId)
{
    $blocked = BlockedUser::where(function ($q) use ($userId) {
        $q->where('blocker_id', auth()->id())
          ->where('blocked_id', $userId);
    })
    ->orWhere(function ($q) use ($userId) {
        $q->where('blocker_id', $userId)
          ->where('blocked_id', auth()->id());
    })
    ->exists();

    if ($blocked) {
    return response()->json([
        'message' => 'You cannot message this user'
    ], 403);
}

    return response()->json([
        'blocked' => $blocked
    ]);
}





}


