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
use App\Models\Group;
use App\Models\MessageFile;
use Illuminate\Support\Facades\Hash;
use App\Services\MessageCryptoService;
use Illuminate\Support\Str;

class ChatController extends Controller
{


public function messages(Chat $chat)
{
    $userId = auth()->id();
        $isAdmin = DB::table('chat_user')
            ->where('chat_id', $chat->id)
            ->where('user_id', $userId)
            ->where('role', 'admin')
            ->exists();
        $onlyAdminCanSend = $chat->only_admin_send ?? false;
            if ($chat->isBlockedFor($userId)) {
                return response()->json([
                    'message' => 'This chat is blocked'
                ], 403);
            }
    $myLastReadId = DB::table('chat_user')
        ->where('chat_id', $chat->id)
        ->where('user_id', $userId)
        ->value('last_read_message_id');
    $otherLastReadId = DB::table('chat_user')
        ->where('chat_id', $chat->id)
        ->where('user_id', '!=', $userId)
        ->value('last_read_message_id');
        Message::where('chat_id', $chat->id)
        ->active()
        ->whereNull('delivered_at')
        ->where('sender_id', '!=', $userId)
        ->update([
            'delivered_at' => now()
        ]);
        $membership = DB::table('chat_user')
            ->where('chat_id', $chat->id)
            ->where('user_id', $userId)
            ->first();
        $joinedAt = $membership?->joined_at;
        $messages = Message::where('chat_id', $chat->id)
        ->active()
        ->whereDoesntHave('messageUsers', function ($q) use ($userId) {
            $q->where('user_id', $userId)
            ->where('deleted', 1);
        })
        ->when($joinedAt, function ($query) use ($joinedAt) {
            $query->where(function ($q) use ($joinedAt) {
                $q->where('created_at', '>=', $joinedAt)
                ->orWhere('type', 'system');
            });
        })
        ->with([
            'sender:id,first_name,last_name,role',
            'reactions:id,message_id,user_id,emoji',
            'reactions.user:id,first_name,last_name'
        ])
        ->orderBy('id', 'asc')
        ->get();
            $lastReadId = $myLastReadId ?? 0;
            $lastOpenedAt = DB::table('chat_user')
                ->where('chat_id', $chat->id)
                ->where('user_id', $userId)
                ->value('last_opened_at');
            $unreadCount = Message::where('chat_id', $chat->id)
                ->active()
                ->where('sender_id', '!=', $userId)
                ->where('id', '>', $lastReadId)
                ->count();
            $result = [];
            $groupMap = [];
            $readerUser = DB::table('chat_user')
                ->join('users', 'users.id', '=', 'chat_user.user_id')
                ->where('chat_user.chat_id', $chat->id)
                ->where('chat_user.user_id', '!=', $userId)
                ->select('users.id', 'users.first_name', 'users.last_name')
                ->first();
        foreach ($messages as $msg) {
        $status = 'sent';
        if ($msg->sender_id == $userId) {
            if ($otherLastReadId && $msg->id <= $otherLastReadId) {
                $status = 'read';
            }
            elseif ($msg->delivered_at) {
                $status = 'delivered';
            }
        }
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

        $replyData = null;
        if ($msg->replied_to) {
            // Handle parsing if it arrived as a raw JSON string from SQLite storage
            $repliedArray = is_string($msg->replied_to) 
                ? json_decode($msg->replied_to, true) 
                : $msg->replied_to;

            if (is_array($repliedArray)) {
                $replyData = [
                    'id'      => $repliedArray['id'] ?? null,
                    'type'    => $repliedArray['type'] ?? 'text',
                    'message' => $repliedArray['message'] ?? null, // Passing raw ciphertext message string
                    'iv'      => $repliedArray['iv'] ?? null,      // Passing matching parent vector reference string
                    'sender'  => [
                        'id'         => $repliedArray['sender']['id'] ?? null,
                        'first_name' => $repliedArray['sender']['first_name'] ?? 'User',
                        'last_name'  => $repliedArray['sender']['last_name'] ?? '',
                    ]
                ];
            }
        }

        $base = [
            'id' => $msg->id,
            'chat_id' => $msg->chat_id,
            'sender_id' => $msg->sender_id,
            'type' => $msg->type,
            'message' => $msg->message,
            'iv' => $msg->iv,
            'reactions' => $msg->reactions->map(function ($reaction) {
                return [
                    'id' => $reaction->id,
                    'emoji' => $reaction->emoji,
                    'user_id' => $reaction->user_id,
                    'user' => $reaction->user ? [
                        'id' => $reaction->user->id,
                        'first_name' => $reaction->user->first_name,
                        'last_name' => $reaction->user->last_name,
                    ] : null,
                ];
            }),
            'group_id' => $msg->group_id,
            'sender' => $msg->sender,
            'is_forwarded' => $msg->is_forwarded ?? false,
            'created_at' => $msg->created_at?->toISOString(),
            'status' => $status,
            'delivered_at' => $msg->delivered_at,
            'read_by' => $readBy,
            'read_by_name' => $readByName,
            'replied_to'   => $replyData, 
        ];
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
    try {
    $chatKey = decrypt($chat->chat_key_user1);
    } catch (\Exception $e) {
        $chatKey = base64_encode(random_bytes(32));
        $chat->update([
            'chat_key_user1' => encrypt($chatKey),
            'chat_key_user2' => encrypt($chatKey),
        ]);
    }
    return response()->json([
    'messages' => $result,
    'last_read_message_id' => $myLastReadId,
    'unread_count' => $unreadCount,
    'is_admin' => $isAdmin,
    'only_admin_can_send' => $onlyAdminCanSend,
    'chat_key' => $chatKey,
]);
}

public function deleteChat(Chat $chat)
{
    $userId = auth()->id();

    // ✅ CHECK USER EXISTS IN CHAT
    $member = DB::table('chat_user')
        ->where('chat_id', $chat->id)
        ->where('user_id', $userId)
        ->first();

    if (!$member) {
        return response()->json([
            'message' => 'Chat not found'
        ], 404);
    }

    // ✅ HIDE CHAT FOR CURRENT USER ONLY
    DB::table('chat_user')
        ->where('chat_id', $chat->id)
        ->where('user_id', $userId)
        ->update([
            'hidden_at' => now(),
        ]);

    return response()->json([
        'message' => 'Chat removed successfully'
    ]);
}


public function oldMessage(Request $request)
{
    $chatId = $request->query('chat_id');
    $before = $request->query('before');

    if (!$chatId) {
        return response()->json([
            'message' => 'chat_id is required'
        ], 422);
    }

    $query = Message::where('chat_id', $chatId);

    // 🔥 load messages older than current first message
    if ($before) {
        $query->where('id', '<', $before);
    }

    $messages = $query
        ->orderBy('id', 'desc')
        ->limit(30)
        ->get()
        ->reverse()
        ->values()
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



//clearChat $chat->messages()

public function index()
{
    $userId = auth()->id();

    $chats = Chat::where(function ($q) use ($userId) {

        $q->where('teacher_id', $userId)
            ->orWhere('student_id', $userId)
            ->orWhere('user_one_id', $userId)
            ->orWhere('user_two_id', $userId)

            ->orWhereHas('users', function ($sub) use ($userId) {
                $sub->where('chat_user.user_id', $userId);
            });

    })

    ->orderByDesc('messages_max_created_at')

    ->with([
        'teacher',
        'student',

        'messages' => function ($q) use ($userId) {
            $q->active()
                ->whereDoesntHave('messageUsers', function ($sub) use ($userId) {
                    $sub->where('user_id', $userId)
                        ->where('deleted', 1);
                })
                ->latest()
                ->limit(1)
                ->with([
                    'sender:id,first_name,last_name',
                    'reader:id,first_name,last_name'
                ]);
        },
    ])
    ->get()

    ->filter(function ($chat) use ($userId) {

        // only groups use membership
        if ($chat->type !== 'group') {
            return true;
        }

        $membership = DB::table('chat_user')
            ->where('chat_id', $chat->id)
            ->where('user_id', $userId)
            ->first();

        // no membership
        if (!$membership) {
            return false;
        }

        if (
            $membership->hidden_at &&
            in_array(
                $membership->status,
                ['left', 'removed', 'rejected']
            )
        ) {
            return false;
        }

        if (
            $membership->hidden_at &&
            in_array(
                $membership->status,
                ['approved', 'pending']
            )
        ) {

            $latestMessage = Message::where('chat_id', $chat->id)
                ->active()
                ->latest()
                ->first();

            // no new message after hidden
            if (
                !$latestMessage ||
                $latestMessage->created_at <=
                $membership->hidden_at
            ) {
                return false;
            }

            DB::table('chat_user')
                ->where('chat_id', $chat->id)
                ->where('user_id', $userId)
                ->update([
                    'hidden_at' => null,
                ]);
        }

        return true;
    })

    ->values();

    $chats->each(function ($chat) use ($userId) {

        $lastReadId = DB::table('chat_user')
            ->where('chat_id', $chat->id)
            ->where('user_id', $userId)
            ->value('last_read_message_id');

        $chat->unread_count = $chat->messages()
            ->active()
            ->whereDoesntHave('messageUsers', function ($q) use ($userId) {
                $q->where('user_id', $userId)
                ->where('deleted', 1);
            })
            ->where('sender_id', '!=', $userId)
            ->where('id', '>', $lastReadId ?? 0)
            ->count();

        $latest = Message::where('chat_id', $chat->id)
                ->active()
                ->whereDoesntHave('messageUsers', function ($q) use ($userId) {
                    $q->where('user_id', $userId)
                    ->where('deleted', 1);
                })
                ->latest()
                ->first();

        $chat->latest_message = $latest;

        unset($chat->messages);

        if ($chat->type === 'group') {

            $chat->group_name = $chat->name;

            $chat->my_role = DB::table('chat_user')
                ->where('chat_id', $chat->id)
                ->where('user_id', $userId)
                ->value('role');

            $chat->membership_status = DB::table('chat_user')
                ->where('chat_id', $chat->id)
                ->where('user_id', $userId)
                ->value('status');

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

            $otherId = $chat->user_one_id == $userId
                ? $chat->user_two_id
                : $chat->user_one_id;

            $chat->other_user = User::find($otherId);
        }

        $block = $chat->blocks()->first();

        $chat->block_info = $block ? [
            'blocked' => true,
            'blocker_id' => $block->blocker_id,
            'blocked_id' => $block->blocked_id,
            'is_blocked_by_me' => $block->blocker_id == $userId,
            'is_blocked_by_other' => $block->blocker_id != $userId,
        ] : null;

        $chat->latest_message_status = $latest
            ? (
                $latest->read_at
                    ? 'read'
                    : (
                        $latest->delivered_at
                            ? 'delivered'
                            : 'sent'
                    )
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
        'iv' => 'nullable|string',
    ]);

    $chat = Chat::findOrFail($request->chat_id);

        if (!$chat->chat_key_user1 || !$chat->chat_key_user2) {

            $chatKey = base64_encode(random_bytes(32));
            $chat->chat_key_user1 = encrypt($chatKey);
            $chat->chat_key_user2 = encrypt($chatKey);
            $chat->save();

        }

        try {
            $chatKey = decrypt($chat->chat_key_user1);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Chat encryption corrupted. Please reinitialize.'
            ], 422);
        }

        if (empty($chatKey)) {
            return response()->json([
                'message' => 'Chat encryption key missing.'
            ], 422);
        }


        $repliedMessage = $request->replied_to ? Message::find($request->replied_to) : null;

        $messageText = $request->message;
        $iv = $request->iv;

        if (!$iv && $request->message) {

            $encrypted = MessageCryptoService::encrypt(
                $request->message,
                $chatKey
            );

            $messageText = $encrypted['data'];
            $iv = $encrypted['iv'];
        }

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

        $mode = $chat->disappearing_mode;

        $expiresAt = match ($mode) {
            '24h' => now()->addHours(24),
            '7d' => now()->addDays(7),
            '90d' => now()->addDays(90),
            default => null,
        };

        $messages = [];

            logger([
            'chat_id' => $chat->id,
            'mode' => $chat->disappearing_mode,
            'expires_at' => $expiresAt,
            ]);

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
    $repliedData = $repliedMessage ? [
        'id'      => $repliedMessage->id,
        'type'    => $repliedMessage->type,
        'message' => $repliedMessage->message, // The encrypted ciphertext string
        'iv'      => $repliedMessage->iv,      // 🔥 CRITICAL: Pass the parent IV to the frontend!
        'sender'  => $repliedMessage->sender ? [
        'id'         => $repliedMessage->sender->id,
        'first_name' => $repliedMessage->sender->first_name,
        'last_name'  => $repliedMessage->sender->last_name,
        ] : null,
    ] : null;

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
                'message'     => $messageText,
                'iv'          => $iv,
                'file'        => $path,
                'file_name'   => $cleanName,
                'replied_to'  => $repliedData,
                'is_read'     => false,
                'expires_at'  => $expiresAt,
                'group_id'    => $groupId,
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
            'message'     => $messageText,
            'iv'          => $iv,
            'file'        => $path,
            'file_name'   => $cleanName,
            'replied_to'  => $repliedData,
            'is_read'     => false,
            'expires_at'  => $expiresAt,
            'group_id'    => $groupId,
        ]);
    }
    else {
        $messages[] = Message::create([
            'chat_id'     => $chat->id,
            'sender_id'   => auth()->id(),
            'receiver_id' => $receiverId,
            'type'        => 'text',
            'message'     => $messageText,
            'iv'          => $iv,
            'replied_to'  => $repliedData,
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

        // ✅ RESTORE CHAT FOR USERS THAT REMOVED IT
        DB::table('chat_user')
            ->where('chat_id', $chat->id)

            // ✅ don't restore for sender
            ->where('user_id', '!=', auth()->id())

            ->update([
                'hidden_at' => null,
            ]);

        $message->load(['sender']);
        broadcast(new NewMessage($message))->toOthers();
    }
    $grouped = collect($messages)
    ->groupBy('group_id')
    ->map(function ($group) {

    $first = $group->first();

    return [

        'id' => $first->id,
        'chat_id' => $first->chat_id,
        'sender_id' => $first->sender_id,
        'receiver_id' => $first->receiver_id,

        'type' => $first->type,

        'message' => $first->message,
        'iv' => $first->iv,

        'group_id' => $first->group_id,

        'sender' => $first->sender,

        'created_at' => $first->created_at,
        'updated_at' => $first->updated_at,

        'replied_to' => $first->replied_to,

        'files' => $group->map(fn($msg) => [
            'file_url' => $msg->file
                ? asset('storage/' . $msg->file)
                : null,

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

        // ===============================
        // ENSURE CHAT KEY EXISTS (SAFE)
        // ===============================
        if (!$chat->chat_key_user1 || !$chat->chat_key_user2) {

            $chatKey = base64_encode(random_bytes(32)); // 256-bit key

            $chat->chat_key_user1 = $chatKey;
            $chat->chat_key_user2 = $chatKey;

            $chat->save();
        }

        // ===============================
        // DECRYPT SAFELY
        // ===============================
        try {
            $chatKey = decrypt($chat->chat_key_user1);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Chat encryption corrupted. Please reinitialize.'
            ], 422);
        }

        // HARD GUARD
        if (empty($chatKey)) {
            return response()->json([
                'message' => 'Chat encryption key missing.'
            ], 422);
        }

    $messageText = $request->message;
    $iv = $request->iv;

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
    
    
    $mode = $chat->disappearing_mode;

    $expiresAt = match ($mode) {
        '24h' => now()->addHours(24),
        '7d' => now()->addDays(7),
        '90d' => now()->addDays(90),
        default => null,
    };

    $message = Message::create([
        'chat_id'   => $chat->id,
        'sender_id' => auth()->id(),
        'type'      => 'voice',
        'receiver_id'=> $receiverId,
        'file'      => $path,
        'replied_to' => $request->replied_to,
        'expires_at' => $expiresAt,
        'is_read' => false,
        'message' => $messageText,
        'iv' => $iv,
            
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

    // ✅ Sender → delete for everyone
    if ($message->sender_id === $userId) {

        $message->update([
            'deleted' => true,
            'message' => null,
            'file' => null,
        ]);

        return response()->json([
            'message' => 'Message deleted for everyone',
            'deleted_for_everyone' => true,
            'message_id' => $message->id,
        ]);
    }

    // ✅ Other user → delete only for me
    $message->users()
        ->updateExistingPivot($userId, [
            'deleted' => true
        ]);

    return response()->json([
        'message' => 'Message deleted for you',
        'deleted_for_everyone' => false,
        'message_id' => $message->id,
    ]);
}

// Clear all message function


public function clearChat(Chat $chat)
{
    $userId = auth()->id();


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
public function edit(Request $request, Message $message)
{
    abort_if($message->sender_id !== auth()->id(), 403);

    abort_if(
        !in_array($message->type, [
            'text',
            'image',
            'video'
        ]),
        403
    );

    abort_if($message->seen_at, 403);

    $request->validate([
        'message' => 'required|string',
        'iv' => 'required|string',
    ]);

    $message->update([
        'message' => $request->message,
        'iv' => $request->iv,
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
        'message_ids' => 'required|array',
        'message_ids.*' => 'exists:messages,id',

        'targets' => 'required|array',
        'targets.*.id' => 'required|integer',
        'targets.*.type' => 'required|in:user,group',
    ]);

    $authId = auth()->id();

    $messages = Message::with('files')
    ->whereIn('id', $request->message_ids)
    ->get();

    $lastChat = null; // 🔥 IMPORTANT

    foreach ($request->targets as $target) {

        // =========================
        // USER PRIVATE CHAT
        // =========================
        if ($target['type'] === 'user') {

            $otherUserId = $target['id'];

            if ($otherUserId == $authId) continue;

            $pair = $this->getChatPair($authId, $otherUserId);

            $chat = Chat::where('user_one_id', $pair['user_one_id'])
                ->where('user_two_id', $pair['user_two_id'])
                ->first();

            if (!$chat) {
                $chat = Chat::create([
                    'user_one_id' => $pair['user_one_id'],
                    'user_two_id' => $pair['user_two_id'],
                    'type' => 'private'
                ]);
            }

            foreach ($messages as $msg) {

    // 1. CREATE MESSAGE
                $newMessage = $chat->messages()->create([
                    'sender_id' => $authId,
                    'type' => $msg->type,
                    'message' => $msg->message,
                    'file' => $msg->file,
                    'is_forwarded' => true,
                ]);

                // 2. ATTACH FILE (ONLY IF EXISTS)
                if ($msg->file) {
                    $newMessage->files()->create([
                        'file_url' => asset('storage/' . $msg->file),
                        'file_name' => $msg->file_name,
                        'type' => $msg->type,
                    ]);
                }
            }

            $lastChat = $chat; // 🔥 store last chat
        }
            // =========================
            // GROUP CHAT FORWARD (FIXED)
            // =========================
            if ($target['type'] === 'group') {

            $chat = Chat::where('id', $target['id'])
                ->where('type', 'group')
                ->first();

            if (!$chat) {
                logger('GROUP CHAT NOT FOUND', ['id' => $target['id']]);
                continue;
            }

            foreach ($messages as $msg) {

            // 1. CREATE MESSAGE
            $newMessage = $chat->messages()->create([
                'sender_id' => $authId,
                'type' => $msg->type,
                'message' => $msg->message,
                'file' => $msg->file,
                'is_forwarded' => true,
            ]);

            // 2. ATTACH FILE (ONLY IF EXISTS)
            if ($msg->file) {
                $newMessage->files()->create([
                    'file_url' => asset('storage/' . $msg->file),
                    'file_name' => $msg->file_name,
                    'type' => $msg->type,
                ]);
            }
        }
            $lastChat = $chat;
        }
        }


    return response()->json([
                'message' => 'Messages forwarded successfully',
                'chat_id' => $lastChat?->id,
                'chat_type' => $lastChat?->type
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


public function updateDisappearing(
    Request $request,
    Chat $chat
) {

    $request->validate([
        'mode' => 'required|in:24h,7d,90d,off',
    ]);

    $chat->update([
        'disappearing_mode' => $request->mode,
    ]);

    // optional system message
    $chat->messages()->create([
        'sender_id' => auth()->id(),
        'type' => 'system',
        'message' => auth()->user()->first_name .
            ' changed disappearing messages to ' .
            $request->mode,
    ]);

    return response()->json([
        'success' => true,
        'mode' => $chat->disappearing_mode,
    ]);
}




public function twoStepStatus()
{
    $user = auth()->user();

    return response()->json([
        'enabled' => $user->two_step_enabled,
    ]);
}

public function setupTwoStep(Request $request)
{
    $request->validate([
        'pin' => 'required|digits:6',
    ]);

    $user = auth()->user();

    $user->update([
        'two_step_pin' => Hash::make($request->pin),
        'two_step_enabled' => true,
    ]);

    return response()->json([
        'message' => 'Two-step verification enabled',
    ]);
}

public function changeTwoStep(Request $request)
{
    $request->validate([
        'pin' => 'required|digits:6',
    ]);

    $user = auth()->user();

    $user->update([
        'two_step_pin' => Hash::make($request->pin),
    ]);

    return response()->json([
        'message' => 'PIN updated successfully',
    ]);
}

public function removeTwoStep()
{
    $user = auth()->user();

    $user->update([
        'two_step_pin' => null,
        'two_step_enabled' => false,
    ]);

    return response()->json([
        'message' => 'Two-step verification disabled',
    ]);
}


    public function verifyEncryption(Chat $chat)
        {
            $chat->update([
                'is_verified' => true
            ]);

            return response()->json([
                'message' => 'Encryption verified'
            ]);
        }


    public function getEncryption(Chat $chat)
    {
        return response()->json([
            'security_code' => Str::random(60),
            'chat_id' => $chat->id
        ]);
    }

    public function autoVerify(Chat $chat)
    {
        return response()->json([
            'verified' => true,
            'chat_id' => $chat->id
        ]);
    }


}