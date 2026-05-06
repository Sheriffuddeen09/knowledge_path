<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Chat;
use App\Models\Message;
use App\Models\MessageDownload;
use App\Events\NewMessage;
use Illuminate\Support\Facades\Storage;
use App\Events\TypingEvent;
use App\Models\User;
use App\Models\Block;
use App\Models\MessageReport;
use App\Models\MessageReaction;
use App\Events\MessageSent;
use ProtoneMedia\LaravelFFMpeg\Support\FFMpeg;
use FFMpeg\Coordinate\TimeCode;
use FFMpeg\Format\Video\X264;
use App\Helpers\VideoHelper;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class ChatController extends Controller
{



public function messages(Chat $chat)
{
    $userId = auth()->id();

    // ✅ check if current user is admin
        $isAdmin = DB::table('chat_user')
            ->where('chat_id', $chat->id)
            ->where('user_id', $userId)
            ->where('role', 'admin')
            ->exists();

        // ✅ check if only admin can send
        $onlyAdminCanSend = $chat->only_admin_send ?? false;
            if ($chat->isBlockedFor($userId)) {
                return response()->json([
                    'message' => 'This chat is blocked'
                ], 403);
            }

    // ✅ MY LAST READ
    $myLastReadId = DB::table('chat_user')
        ->where('chat_id', $chat->id)
        ->where('user_id', $userId)
        ->value('last_read_message_id');

    // ✅ OTHER USER LAST READ (🔥 THIS IS THE FIX)
    $otherLastReadId = DB::table('chat_user')
        ->where('chat_id', $chat->id)
        ->where('user_id', '!=', $userId)
        ->value('last_read_message_id');

    // ✅ mark delivered
    Message::where('chat_id', $chat->id)
        ->whereNull('delivered_at')
        ->where('sender_id', '!=', $userId)
        ->update([
            'delivered_at' => now()
        ]);

    $messages = Message::where('chat_id', $chat->id)
        ->with([
            'sender:id,first_name,last_name,role',
            'replyTo:id,chat_id,message,type,file,file_name,sender_id',
            'replyTo.sender:id,first_name,last_name'
        ])
        ->orderBy('id', 'asc')
        ->get();

    // ✅ unread count (based on MY read)
    $unreadCount = Message::where('chat_id', $chat->id)
        ->where('sender_id', '!=', $userId)
        ->where('id', '>', $myLastReadId ?? 0)
        ->count();

    $result = [];
    $groupMap = [];

    // ✅ get reader info
    $readerUser = DB::table('chat_user')
        ->join('users', 'users.id', '=', 'chat_user.user_id')
        ->where('chat_user.chat_id', $chat->id)
        ->where('chat_user.user_id', '!=', $userId)
        ->select('users.id', 'users.first_name', 'users.last_name')
        ->first();

    foreach ($messages as $msg) {

        // ================= STATUS =================
        $status = 'sent';

        if ($msg->sender_id == $userId) {

            // ✅ READ (OTHER USER HAS READ IT)
            if ($otherLastReadId && $msg->id <= $otherLastReadId) {
                $status = 'read';
            }

            // ✅ DELIVERED
            elseif ($msg->delivered_at) {
                $status = 'delivered';
            }
        }

        // ================= READER =================
        $readBy = null;
        $readByName = null;

        if ($status === 'read' && $readerUser) {
            $readBy = [
                'id' => $readerUser->id,
                'first_name' => $readerUser->first_name,
                'last_name' => $readerUser->last_name,
            ];

            $readByName = $readerUser->first_name . ' ' . $readerUser->last_name;
        }

        // ================= BASE =================
        $base = [
            'id' => $msg->id,
            'chat_id' => $msg->chat_id,
            'sender_id' => $msg->sender_id,
            'type' => $msg->type,
            'message' => $msg->message,
            'group_id' => $msg->group_id,
            'sender' => $msg->sender,
            'is_forwarded' => $msg->is_forwarded ?? false,
            'created_at' => $msg->created_at?->toISOString(),

            'status' => $status,
            'delivered_at' => $msg->delivered_at,

            // ✅ IMPORTANT FOR FRONTEND
            'read_by' => $readBy,
            'read_by_name' => $readByName,

            'replied_to' => $msg->replyTo ? [
                'id' => $msg->replyTo->id,
                'type' => $msg->replyTo->type,
                'message' => $msg->replyTo->message,
                'file_url' => $msg->replyTo->file
                    ? asset('storage/' . $msg->replyTo->file)
                    : null,
                'file_name' => $msg->replyTo->file_name,
                'sender' => $msg->replyTo->sender ? [
                    'id' => $msg->replyTo->sender->id,
                    'first_name' => $msg->replyTo->sender->first_name,
                    'last_name' => $msg->replyTo->sender->last_name,
                ] : null,
            ] : null,
        ];

        // ================= GROUP =================
        if ($msg->group_id) {

            if (!isset($groupMap[$msg->group_id])) {
                $groupMap[$msg->group_id] = $base;
                $groupMap[$msg->group_id]['files'] = [];
                $result[] = &$groupMap[$msg->group_id];
            }

            $groupMap[$msg->group_id]['files'][] = [
                'file_url' => $msg->file
                    ? asset('storage/' . $msg->file)
                    : null,
                'file_name' => $msg->file_name,
                'type' => $msg->type,
            ];

        } else {

            $base['files'] = [[
                'file_url' => $msg->file
                    ? asset('storage/' . $msg->file)
                    : null,
                'file_name' => $msg->file_name,
                'type' => $msg->type,
            ]];

            $result[] = $base;
        }
    }

    return response()->json([
    'messages' => $result,
    'last_read_message_id' => $myLastReadId,
    'unread_count' => $unreadCount,

    // ✅ ADD THESE
    'is_admin' => $isAdmin,
    'only_admin_can_send' => $onlyAdminCanSend,
]);
}


public function oldMessage(Request $request)
{
    $chatId = $request->query('chat_id');
    $before = $request->query('before');

    if (!$chatId) {
        return response()->json(['message' => 'chat_id is required'], 422);
    }

    $query = Message::where('chat_id', $chatId);

    if ($before) {
        $query->where('id', '<', $before);
    }

    $messages = $query
        ->orderBy('id', 'desc')
        ->limit(20)
        ->get()
        ->map(function ($msg) {
            return [
                ...$msg->toArray(),
                'created_at' => $msg->created_at?->toISOString(),

                'status' => match (true) {
                    !is_null($msg->read_at) => 'read',
                    !is_null($msg->delivered_at) => 'delivered',
                    default => 'sent',
                },
            ];
        });

    return response()->json([
        'data' => $messages
    ]);
}

public function index()
{
    $userId = auth()->id();

    $chats = Chat::where(function ($q) use ($userId) {

        // ✅ PRIVATE CHATS
        $q->where('teacher_id', $userId)
          ->orWhere('student_id', $userId)
          ->orWhere('user_one_id', $userId)
          ->orWhere('user_two_id', $userId)

          // ✅ GROUP CHATS (FIXED - SIMPLE + CORRECT)
          ->orWhereHas('users', function ($sub) use ($userId) {
              $sub->where('chat_user.user_id', $userId);
          });

    })
    ->withMax('messages', 'created_at')
    ->orderByDesc('messages_max_created_at')
    ->with([
        'teacher',
        'student',
        'messages' => function ($q) {
            $q->latest()
              ->limit(1)
              ->with([
                  'sender:id,first_name,last_name',
                  'reader:id,first_name,last_name'
              ]);
        },
    ])
    ->get();

    $chats->each(function ($chat) use ($userId) {

        // =========================
        // UNREAD COUNT
        // =========================
        $chat->unread_count = $chat->messages()
            ->whereNull('read_at')
            ->where('sender_id', '!=', $userId)
            ->count();

        $latest = $chat->messages->first();
        $chat->latest_message = $latest;

        unset($chat->messages);

        // =========================
        // GROUP CHAT LOGIC
        // =========================
        if ($chat->type === 'group') {

            $chat->group_name = $chat->name;

            // 🔥 MY ROLE (admin/member)
            $chat->my_role = DB::table('chat_user')
                ->where('chat_id', $chat->id)
                ->where('user_id', $userId)
                ->value('role');

            // 🔥 MEMBERSHIP STATUS (pending/approved/rejected)
            $chat->membership_status = DB::table('chat_user')
                ->where('chat_id', $chat->id)
                ->where('user_id', $userId)
                ->value('status');

            // =========================
            // MEMBERS LIST (ONLY ACTIVE)
            // =========================
            $chat->members = DB::table('chat_user')
                ->join('users', 'users.id', '=', 'chat_user.user_id')
                ->where('chat_user.chat_id', $chat->id)
                ->where(function ($q) {
                    $q->where('chat_user.status', 'approved')
                      ->orWhere('chat_user.role', 'admin');
                })
                ->select(
                    'users.id',
                    'users.first_name',
                    'users.last_name',
                    'chat_user.role as role'
                )
                ->get();

        } else {

            // =========================
            // PRIVATE CHAT LOGIC
            // =========================
            $otherId = $chat->user_one_id == $userId
                ? $chat->user_two_id
                : $chat->user_one_id;

            $chat->other_user = User::find($otherId);
        }

        // =========================
        // BLOCK INFO
        // =========================
        $block = $chat->blocks()->first();

        $chat->block_info = $block ? [
            'blocked' => true,
            'blocker_id' => $block->blocker_id,
            'blocked_id' => $block->blocked_id,
            'is_blocked_by_me' => $block->blocker_id == $userId,
            'is_blocked_by_other' => $block->blocker_id != $userId,
        ] : null;

        // =========================
        // MESSAGE STATUS
        // =========================
        $chat->latest_message_status = $latest
            ? (
                $latest->read_at
                    ? 'read'
                    : ($latest->delivered_at ? 'delivered' : 'sent')
            )
            : null;

        $chat->latest_message_read_by_name =
            $latest?->reader?->first_name ?? null;
    });

    return response()->json($chats);
}

public function send(Request $request)
{
    $request->validate([
        'chat_id'    => 'required|exists:chats,id',
        'type'       => 'nullable|in:text,image,voice,video,file,audio',
        'types'      => 'nullable|array',
        'types.*'    => 'in:image,video,voice,file,audio',
        'message'    => 'nullable|string',
        'file'       => 'nullable|file|max:20480',
        'files'      => 'nullable|array',
        'files.*'    => 'file|max:20480',
        'trim_start' => 'nullable|array',
        'trim_end'   => 'nullable|array',
        'replied_to' => 'nullable|exists:messages,id',
        'group_id' => 'nullable|string',
    ]);

    
    $chat = Chat::findOrFail($request->chat_id);

    if ($chat->isBlockedFor(auth()->id())) {
        return response()->json(['message' => 'You are blocked in this chat'], 403);
    }
    $receiverId = null;

    if ($chat->teacher_id && $chat->student_id) {
        $receiverId = $chat->teacher_id == auth()->id()
            ? $chat->student_id
            : $chat->teacher_id;
    }

    if ($chat->user_one_id && $chat->user_two_id) {
        $receiverId = $chat->user_one_id == auth()->id()
            ? $chat->user_two_id
            : $chat->user_one_id;
    }

    // 🔥 DISAPPEARING MODE
    $expiresAt = null;
    if ($chat->disappearing_mode && $chat->disappearing_time > 0) {
        $expiresAt = now()->addSeconds($chat->disappearing_time);
    }

    $messages = [];

    // 🔥 SAFE FILE NAME
    $generateFileName = function ($file) {
        $original = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $extension = $file->getClientOriginalExtension();

        $clean = preg_replace('/[^A-Za-z0-9_\-]/', '_', $original);

        return $clean . '_' . time() . '.' . $extension;
    };

    $starts = $request->trim_start ?? [];
    $ends   = $request->trim_end ?? [];
    $types  = $request->types ?? [];

    if ($request->hasFile('files')) {

        $files = $request->file('files');

        // ✅ FIRST: get from frontend
        $groupId = $request->input('group_id');

        if (!$groupId) {
            $onlyMedia = collect($types)->every(fn($t) => in_array($t, ['image', 'video']));

            if ($onlyMedia && count($files) > 1) {
                $groupId = uniqid('grp_');
            }
        }
        foreach ($files as $index => $file) {

            $storedName = $generateFileName($file);
            $type = $types[$index] ?? 'file';

            $start = $starts[$index] ?? 0;
            $end   = $ends[$index] ?? 0;

            $path = null;

            if ($type === 'video' && $end > $start) {
                $tempPath = $file->getRealPath();
                $outputName = 'trimmed_' . $storedName;
                $outputFullPath = storage_path('app/public/chat_files/' . $outputName);

                if (!file_exists(dirname($outputFullPath))) {
                    mkdir(dirname($outputFullPath), 0777, true);
                }
                $command = "ffmpeg -ss $start -i \"$tempPath\" -to $end -c:v libx264 -c:a aac \"$outputFullPath\" 2>&1";
                exec($command, $output, $returnCode);
                if ($returnCode !== 0) {
                    // ❌ fallback if ffmpeg fails
                    $path = $file->storeAs('chat_files', $storedName, 'public');
                } else {
                    $path = 'chat_files/' . $outputName;
                }
            } else {
                // 📁 NORMAL FILE / IMAGE
                $path = $file->storeAs('chat_files', $storedName, 'public');
            }

            $originalName = $file->getClientOriginalName();
            $cleanName = preg_replace('/[^A-Za-z0-9_\-\.]/', '_', $originalName);

            
            $messages[] = Message::create([
                'chat_id'     => $chat->id,
                'sender_id'   => auth()->id(),
                'receiver_id' => $receiverId,
                'type'        => $type,
                'message'     => $request->message,
                'file'        => $path,
                'file_name' => $cleanName,
                'replied_to'  => $request->replied_to,
                'is_read'     => false,
                'expires_at'  => $expiresAt,
                'group_id' => $groupId,
            ]);
        }
    }
    elseif ($request->hasFile('file')) {
        $file = $request->file('file');
        $storedName = $generateFileName($file);
        $type = $request->type ?? 'file';
        $start = $request->trim_start[0] ?? 0;
        $end   = $request->trim_end[0] ?? 0;

        $path = null;

        if ($type === 'video' && $end > $start) {
            $tempPath = $file->getRealPath();
            $outputName = 'trimmed_' . $storedName;
            $outputFullPath = storage_path('app/public/chat_files/' . $outputName);

            if (!file_exists(dirname($outputFullPath))) {
                mkdir(dirname($outputFullPath), 0777, true);
            }
            $command = "ffmpeg -ss $start -i \"$tempPath\" -to $end -c:v libx264 -c:a aac \"$outputFullPath\" 2>&1";
            exec($command, $output, $returnCode);
            if ($returnCode !== 0) {
                $path = $file->storeAs('chat_files', $storedName, 'public');
            } else {
                $path = 'chat_files/' . $outputName;
            }

        } else {
            $path = $file->storeAs('chat_files', $storedName, 'public');
        }
        $messages[] = Message::create([
            'chat_id'     => $chat->id,
            'sender_id'   => auth()->id(),
            'receiver_id' => $receiverId,
            'type'        => $type,
            'message'     => $request->message,
            'file'        => $path,
            'file_name'   => $file->getClientOriginalName(),
            'replied_to'  => $request->replied_to,
            'is_read'     => false,
            'expires_at'  => $expiresAt,
        ]);
    }
    else {
        $messages[] = Message::create([
            'chat_id'     => $chat->id,
            'sender_id'   => auth()->id(),
            'receiver_id' => $receiverId,
            'type'        => 'text',
            'message'     => $request->message,
            'replied_to'  => $request->replied_to,
            'is_read'     => false,
            'expires_at'  => $expiresAt,
        ]);
    }
    foreach ($messages as $message) {

        $userIds = collect([
            $chat->teacher_id,
            $chat->student_id,
            $chat->user_one_id,
            $chat->user_two_id
        ])->filter()->unique();

        foreach ($userIds as $userId) {
            $message->users()->attach($userId, ['deleted' => false]);
        }
        $message->load(['sender', 'repliedMessage.sender']);
        broadcast(new NewMessage($message))->toOthers();
    }
    $grouped = collect($messages)
    ->groupBy('group_id')
    ->map(function ($group) {
        $first = $group->first();
        return [
            ...$first->toArray(),
            'group_id' => $first->group_id,
            'files' => $group->map(fn($msg) => [
                'file_url' => asset('storage/' . $msg->file),
                'file_name' => $msg->file_name,
                'type' => $msg->type,
            ])->values(),
        ];
    })
    ->values();
return response()->json([
    'messages' => $grouped
]);
}


// sendvoice note function block
   public function sendVoice(Request $request)
{
    $request->validate([
        'chat_id' => 'required|exists:chats,id',
        'voice' => 'required|file|mimes:webm,mp3,wav,ogg',
        'replied_to' => 'nullable|exists:messages,id',

    ]);

    $chat = Chat::findOrFail($request->chat_id);

    // 🔒 BLOCK CHECK
    if ($chat->isBlockedFor(auth()->id())) {
        return response()->json([
            'message' => 'You are blocked in this chat'
        ], 403);
    }

    $file = $request->file('voice');
    // $path = $file->store('chat_files', 'public');
    $path = $request->file('voice')->store('voices', 'public');

    $receiverId = $chat->teacher_id == auth()->id()
    ? $chat->student_id
    : $chat->teacher_id;

    if ($chat->user_one_id && $chat->user_two_id) {
        $receiverId = $chat->user_one_id == auth()->id()
            ? $chat->user_two_id
            : $chat->user_one_id;
    }
    
    
    $expiresAt = null;

    if ($chat->disappearing_mode && $chat->disappearing_time > 0) {
            $expiresAt = now()->addSeconds($chat->disappearing_time);
        }

    $message = Message::create([
        'chat_id'   => $chat->id,
        'sender_id' => auth()->id(),
        'type'      => 'voice',
        'receiver_id'=> $receiverId,
        'file'      => $path,
        'replied_to' => $request->replied_to,
        'expires_at' => $expiresAt,
        'is_read' => false,

    ]);

    $message->load('sender');

    broadcast(new NewMessage($message))->toOthers();

    return response()->json([
        'message' => $message,
    ]);
}


public function setDisappearing(Request $request, $chatId)
{
    $chat = Chat::findOrFail($chatId);

    $chat->disappearing_enabled = $request->enabled;
    $chat->disappearing_seconds = $request->time;

    $chat->save();

    return response()->json([
        'success' => true,
        'message' => 'Disappearing settings updated'
    ]);
    
}


// markSeen function


public function markSeen($chatId)
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



 //forward function 

public function forwardMultiple(Request $request)
{
    $request->validate([
        'user_ids' => 'nullable|array',
        'user_ids.*' => 'exists:users,id',

        'group_ids' => 'nullable|array',
        'group_ids.*' => 'exists:chats,id',

        'message_ids' => 'required|array',
        'message_ids.*' => 'exists:messages,id',
    ]);

    if (empty($request->user_ids) && empty($request->group_ids)) {
        return response()->json([
            'message' => 'No recipients selected'
        ], 422);
    }

    $authId = auth()->id();

    // ✅ ONLY selected messages
    $messagesToForward = Message::whereIn('id', $request->message_ids)->get();

    $createdMessages = [];

    /*
    |--------------------------------------------------------------------------
    | 🔥 FORWARD TO GROUPS
    |--------------------------------------------------------------------------
    */
    foreach ($request->group_ids ?? [] as $groupId) {

        $chat = Chat::find($groupId);
        if (!$chat || $chat->type !== 'group') continue;

        foreach ($messagesToForward as $original) {

            // ✅ FIX: always trace back to root message
            $rootId = $original->forwarded_from ?? $original->id;

            $msg = Message::create([
                'chat_id'   => $chat->id,
                'sender_id' => $authId,

                'type'      => $original->type,
                'message'   => $original->message,

                'file'      => $original->file,
                'file_name' => $original->file_name,

                'group_id'  => null,

                'forwarded_from' => $rootId, // 🔥 KEY FIX
                'is_forwarded'   => true,
            ]);

            $msg->load(['sender:id,first_name,last_name']);
            broadcast(new NewMessage($msg))->toOthers();

            $createdMessages[] = $msg;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | 🔥 FORWARD TO USERS
    |--------------------------------------------------------------------------
    */
    foreach ($request->user_ids ?? [] as $userId) {

        $chat = Chat::firstOrCreate(
            $this->getChatPair($authId, $userId)
        );

        foreach ($messagesToForward as $original) {

            // ✅ FIX: always trace back to root
            $rootId = $original->forwarded_from ?? $original->id;

            $msg = Message::create([
                'chat_id'     => $chat->id,
                'sender_id'   => $authId,
                'receiver_id' => $userId,

                'type'        => $original->type,
                'message'     => $original->message,

                'file'        => $original->file,
                'file_name'   => $original->file_name,

                'group_id'    => null,

                'forwarded_from' => $rootId, // 🔥 KEY FIX
                'is_forwarded'   => true,
            ]);

            $msg->load(['sender:id,first_name,last_name']);
            broadcast(new NewMessage($msg))->toOthers();

            $createdMessages[] = $msg;
        }
    }

    return response()->json([
        'success' => true,
        'messages' => $createdMessages
    ]);
}


private function getChatPair($userA, $userB)
{
    return [
        'user_one_id' => min($userA, $userB),
        'user_two_id' => max($userA, $userB),
    ];
}

// React Function react

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

// react

public function markAsReadMessage(Request $request)
{
    Message::where('receiver_id', auth()->id())
        ->where('is_read', false)
        ->update([
            'is_read' => true
        ]);

    return response()->json([
        'status' => true
    ]);
}


public function download(Request $request, $type, $messageId)
{
    $user = $request->user();

    $allowed = ['video', 'image', 'audio', 'document'];

    if (!in_array($type, $allowed)) {
        return response()->json(['error' => 'Invalid type'], 400);
    }

    // 🔥 GET MESSAGE (NOT POST)
    $message = Message::findOrFail($messageId);

    // 🔥 Ensure message contains media
    if ($message->type !== $type) {
        return response()->json(['error' => 'Type mismatch'], 400);
    }

    // 🔥 file path
    $path = storage_path('app/public/' . $message->file);

    if (!file_exists($path)) {
        return response()->json(['error' => 'File not found'], 404);
    }

    // ✅ track download (MESSAGE VERSION)
    if ($user) {
        MessageDownload::updateOrCreate(
            [
                'user_id' => $user->id,
                'message_id' => $message->id,
            ],
            [
                'downloaded_at' => now()
            ]
        );
    }

    // 🎯 file name
    $fileName = match ($type) {
        'video' => 'IPK-video.mp4',
        'image' => 'IPK-image.jpg',
        'audio' => 'IPK-audio.mp3',
        'document' => 'IPK-document.pdf',
        default => 'download'
    };

    // 🎯 mime type
    $mime = match ($type) {
        'video' => 'video/mp4',
        'image' => 'image/jpeg',
        'audio' => 'audio/mpeg',
        'document' => 'application/pdf',
        default => 'application/octet-stream'
    };

    return response()->download($path, $fileName, [
        'Content-Type' => $mime
    ]);
}

public function markAsRead($id)
{
    $userId = auth()->id();

    $message = Message::where('id', $id)
        ->where('sender_id', '!=', $userId)
        ->firstOrFail();

    if (!$message->read_at) {
        $message->update([
            'read_at' => now()
        ]);
    }

    return response()->json(['success' => true]);
}

public function pin(Request $request)
{
    $message = Message::findOrFail($request->message_id);

    $message->is_pinned = true;
    $message->save();

    return response()->json([
        'message' => 'Message pinned successfully',
        'data' => $message
    ]);
}

public function unpin(Request $request)
{
    $message = Message::findOrFail($request->message_id);

    $message->is_pinned = false;
    $message->save();

    return response()->json([
        'message' => 'Message unpinned successfully',
        'data' => $message
    ]);
}


public function markAsReadChat($chatId)
{
    $userId = auth()->id();

    // ✅ get last message in chat
    $lastMessage = Message::where('chat_id', $chatId)
        ->latest('id')
        ->first();

    if (!$lastMessage) {
        return response()->json(['success' => true]);
    }

    // ✅ update pivot table
    DB::table('chat_user')->updateOrInsert(
        [
            'chat_id' => $chatId,
            'user_id' => $userId,
        ],
        [
            'last_read_message_id' => $lastMessage->id,
            'updated_at' => now(),
        ]
    );

    Message::where('chat_id', $chatId)
        ->whereNull('read_at')
        ->where('sender_id', '!=', $userId)
        ->update([
            'read_at' => now()
        ]);

    return response()->json([
        'success' => true,
        'last_read_message_id' => $lastMessage->id
    ]);
}




}