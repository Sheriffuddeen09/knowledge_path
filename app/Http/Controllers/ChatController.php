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


   public function messages(Chat $chat)
{
    $userId = auth()->id();

    // Mark messages as delivered for the current user
    $chat->messages()
        ->whereNull('delivered_at')
        ->where('sender_id', '!=', $userId)
        ->update(['delivered_at' => now()]);

    // Return messages with sender info including role
    return $chat->messages()
        ->with('sender:id,first_name,last_name,role') // make sure role is included
        ->orderBy('created_at')
        ->get();
}



    // Send message
   public function send(Request $request)
{
    $request->validate([
        'chat_id' => 'required|exists:chats,id',
        'type' => 'required|string',
        'message' => 'nullable|string',
        'file' => 'nullable|file|max:20480', // 20MB
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
    ]);

    $message->load('sender');

    broadcast(new NewMessage($message))->toOthers();

    return $message;
}

    // Edit message
    public function edit(Message $message, Request $request)
    {
        abort_if($message->sender_id !== auth()->id(), 403);

        $message->update([
            'message' => $request->message,
            'edited' => true
        ]);

        return $message;
    }

    // Delete message
    public function delete(Message $message)
    {
        abort_if($message->sender_id !== auth()->id(), 403);
        $message->delete();

        return response()->noContent();
    }


   
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


public function markSeen(Message $message)
{
    abort_if($message->sender_id === auth()->id(), 403);

    $message->update(['seen_at' => now()]);

    broadcast(new MessageSeen($message))->toOthers();

    return response()->noContent();
}


public function typing(Request $request)
{
    broadcast(new TypingEvent(
        $request->chat_id,
        auth()->id()
    ))->toOthers();

    return response()->noContent();
}

public function react(Request $request, Message $message)
{
    $reaction = $message->reactions()->updateOrCreate(
        ['user_id' => auth()->id()],
        ['reaction' => $request->reaction]
    );

    broadcast(new Message($message->id, $reaction))->toOthers();

    return $reaction;
}



public function destroy(Message $message)
{
  abort_if($message->sender_id !== auth()->id(), 403);
  $message->delete();
  return response()->json(['success' => true]);
}
public function update(Request $request, Message $message)
{
  abort_if($message->sender_id !== auth()->id(), 403);

  $request->validate(['message' => 'required|string']);

  $message->update([
    'message' => $request->message,
    'edited' => true,
  ]);

  return response()->json($message);
}

public function toggleBlock(Request $request)
{
  Block::firstOrCreate([
    'user_id' => auth()->id(),
    'blocked_user_id' => $request->user_id,
  ]);

  return response()->json(['blocked' => true]);
}

public function clearChat(Chat $chat)
{
  $chat->messages()->delete();
  return response()->json(['success' => true]);
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


