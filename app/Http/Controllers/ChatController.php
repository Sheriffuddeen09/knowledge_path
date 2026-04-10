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
use ProtoneMedia\LaravelFFMpeg\Support\FFMpeg;
use FFMpeg\Coordinate\TimeCode;

   

class ChatController extends Controller
{


// public function messages(Chat $chat)
// {
//     $userId = auth()->id();

//     if ($chat->isBlockedFor($userId)) {
//         return response()->json(['message' => 'This chat is blocked'], 403);
//     }

//     $chat->messages()
//         ->whereNull('delivered_at')
//         ->where('sender_id', '!=', $userId)
//         ->update(['delivered_at' => now()]);

//     $chat->messages()
//         ->whereNull('is_read')
//         ->where('sender_id', '!=', $userId)
//         ->update(['is_read' => now()]);

//     return $chat->messages()
//         ->where(function ($q) {
//             $q->whereNull('expires_at')
//               ->orWhere('expires_at', '>', now());
//         })
//         ->with([
//             'sender:id,first_name,last_name,role',
//             'reactions.user:id,first_name,last_name',
//             'repliedMessage.sender:id,first_name,last_name,role',
//         ])
//         ->orderBy('created_at')
//         ->get()
//         ->map(function ($msg) {
//                 return [
//                     ...$msg->toArray(),
//                     'created_at' => $msg->created_at?->toISOString() ?? null,

//                     // 🔥 FIX: derive status from DB fields
//                     'status' => match (true) {
//                         $msg->is_read => 'read',
//                         !is_null($msg->delivered_at) => 'delivered',
//                         default => 'sent',
//                     },
//                 ];
//             });
// }

public function messages(Chat $chat)
{
    $userId = auth()->id();

    if ($chat->isBlockedFor($userId)) {
        return response()->json(['message' => 'This chat is blocked'], 403);
    }

    // mark delivered
    $chat->messages()
        ->whereNull('delivered_at')
        ->where('sender_id', '!=', $userId)
        ->update(['delivered_at' => now()]);

    // mark read
    $chat->messages()
        ->whereNull('is_read')
        ->where('sender_id', '!=', $userId)
        ->update(['is_read' => now()]);

    return $chat->messages()
        ->where(function ($q) {
            $q->whereNull('expires_at')
              ->orWhere('expires_at', '>', now());
        })
        ->with([
            'sender:id,first_name,last_name,role',
            'reactions.user:id,first_name,last_name',
            'repliedMessage.sender:id,first_name,last_name,role',
        ])
        ->orderBy('created_at')
        ->get()
        ->map(function ($msg) {

            // ✅ FIX: FORCE FILES ALWAYS ARRAY
            $files = [];

            if (!empty($msg->files) && is_array($msg->files)) {
                $files = $msg->files;
            }

            // fallback for OLD messages (single file system)
            elseif (!empty($msg->file) || !empty($msg->file_url)) {
                $files = [[
                    'file' => $msg->file,
                    'file_url' => $msg->file_url,
                    'type' => $msg->type ?? 'image',
                ]];
            }

            return [
                ...$msg->toArray(),

                'files' => $files, // 🔥 THIS IS THE FIX

                'created_at' => $msg->created_at?->toISOString(),

                'status' => match (true) {
                    $msg->is_read => 'read',
                    !is_null($msg->delivered_at) => 'delivered',
                    default => 'sent',
                },
            ];
        });
}

// public function oldMessage(Request $request)
// {
//     $chatId = $request->query('chat_id');
//     $before = $request->query('before'); // last message id for pagination

//     if (!$chatId) {
//         return response()->json(['message' => 'chat_id is required'], 422);
//     }

//     $query = Message::where('chat_id', $chatId);

//     // pagination (load older messages)
//     if ($before) {
//         $query->where('id', '<', $before);
//     }

//     $messages = $query
//         ->orderBy('id', 'desc')
//         ->limit(20)
//         ->get();

//     return response()->json([
//         'data' => $messages
//     ]);
// }


public function oldMessage(Request $request)
{
    $chatId = $request->query('chat_id');
    $before = $request->query('before');

    if (!$chatId) {
        return response()->json(['message' => 'chat_id is required'], 422);
    }

    $messages = Message::where('chat_id', $chatId)
        ->when($before, function ($q) use ($before) {
            $q->where('id', '<', $before);
        })
        ->with([
            'sender:id,first_name,last_name,role',
            'reactions.user:id,first_name,last_name',
            'repliedMessage.sender:id,first_name,last_name,role',
        ])
        ->orderBy('id', 'desc')
        ->limit(20)
        ->get()
        ->map(function ($msg) {

            // ✅ SAME FIX HERE (CRITICAL)
            $files = [];

            if (!empty($msg->files) && is_array($msg->files)) {
                $files = $msg->files;
            }

            elseif (!empty($msg->file) || !empty($msg->file_url)) {
                $files = [[
                    'file' => $msg->file,
                    'file_url' => $msg->file_url,
                    'type' => $msg->type ?? 'image',
                ]];
            }

            return [
                ...$msg->toArray(),
                'files' => $files,
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
            $q->where('teacher_id', $userId)
              ->orWhere('student_id', $userId)
              ->orWhere('user_one_id', $userId)
              ->orWhere('user_two_id', $userId);
        })
        ->with([
            'teacher',
            'student',
            'messages' => fn ($q) => $q->latest()->limit(1),
        ])
        ->get();

    $chats->each(function ($chat) use ($userId) {

        // unread count
        $chat->unread_count = $chat->messages
            ->whereNull('seen_at')
            ->where('sender_id', '!=', $userId)
            ->count();

        // latest message
        $chat->latest_message = $chat->messages->first();
        unset($chat->messages);

        // 👇 DETERMINE OTHER USER (CRITICAL FIX)
        if ($chat->type === 'student_teacher') {
            $chat->other_user = $chat->teacher_id == $userId
                ? $chat->student
                : $chat->teacher;
        } else {
            $otherId = $chat->user_one_id == $userId
                ? $chat->user_two_id
                : $chat->user_one_id;

            $chat->other_user = User::find($otherId);
        }

        $block = $chat->blocks()->first();

        if ($block) {
            $chat->block_info = [
                'blocked' => true,
                'blocker_id' => $block->blocker_id,
                'blocked_id' => $block->blocked_id,
                'is_blocked_by_me' => $block->blocker_id == $userId,
                'is_blocked_by_other' => $block->blocker_id != $userId,
            ];
        } else {
            $chat->block_info = null;
        }
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
    ]);

    $chat = Chat::findOrFail($request->chat_id);

    // 🔒 BLOCK CHECK
    if ($chat->isBlockedFor(auth()->id())) {
        return response()->json([
            'message' => 'You are blocked in this chat'
        ], 403);
    }

    // 🎯 DETERMINE RECEIVER
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

    // ⏳ DISAPPEARING
    $expiresAt = null;
    if ($chat->disappearing_mode && $chat->disappearing_time > 0) {
        $expiresAt = now()->addSeconds($chat->disappearing_time);
    }

    $messages = [];

    // 📦 MULTIPLE FILES
    if ($request->hasFile('files')) {

        $files = $request->file('files');
        $types = $request->types ?? [];
        $trimStarts = $request->trim_start ?? [];
        $trimEnds   = $request->trim_end ?? [];

        foreach ($files as $index => $file) {

            $type = $types[$index] ?? 'file';
            $fileName = $file->getClientOriginalName();

            // 📁 SAVE ORIGINAL FIRST
            $originalPath = $file->store('chat_files', 'public');
            $finalPath = $originalPath;

            // 🎬 VIDEO TRIMMING
            if ($type === 'video' && isset($trimStarts[$index]) && isset($trimEnds[$index])) {

                $start = floatval($trimStarts[$index]);
                $end   = floatval($trimEnds[$index]);

                // ✅ Only trim if user actually trimmed
                if ($start > 0 || $end > $start) {

                    try {
                        $trimmedName = 'trim_' . time() . '_' . $fileName;

                        FFMpeg::fromDisk('public')
                            ->open($originalPath)
                            ->export()
                            ->addFilter(function ($filters) use ($start, $end) {
                                $filters->clip(
                                    TimeCode::fromSeconds($start),
                                    TimeCode::fromSeconds($end - $start)
                                );
                            })
                            ->toDisk('public')
                            ->save('chat_files/' . $trimmedName);

                        // 🔁 Replace original with trimmed
                        $finalPath = 'chat_files/' . $trimmedName;

                    } catch (\Exception $e) {
                        // ❌ fallback to original
                        $finalPath = $originalPath;
                    }
                }
            }

            // 🎧 AUDIO DURATION
            $duration = null;
            if (in_array($type, ['voice', 'audio'])) {
                try {
                    $media = FFMpeg::fromDisk('public')->open($originalPath);
                    $duration = $media->getDurationInSeconds();
                } catch (\Exception $e) {
                    $duration = null;
                }
            }

            $messages[] = Message::create([
                'chat_id'    => $chat->id,
                'sender_id'  => auth()->id(),
                'receiver_id'=> $receiverId,
                'type'       => $type,
                'message'    => $request->message,
                'file'       => $finalPath,
                'file_name'  => $fileName,
                'duration'   => $duration,
                'replied_to' => $request->replied_to,
                'is_read'    => false,
                'expires_at' => $expiresAt,
            ]);
        }
    }

    // 📄 SINGLE FILE
    elseif ($request->hasFile('file')) {

        $file = $request->file('file');
        $path = $file->store('chat_files', 'public');

        $messages[] = Message::create([
            'chat_id'    => $chat->id,
            'sender_id'  => auth()->id(),
            'receiver_id'=> $receiverId,
            'type'       => $request->type,
            'message'    => $request->message,
            'file'       => $path,
            'file_name'  => $file->getClientOriginalName(),
            'replied_to' => $request->replied_to,
            'is_read'    => false,
            'expires_at' => $expiresAt,
        ]);
    }

    // 💬 TEXT
    else {
        $messages[] = Message::create([
            'chat_id'    => $chat->id,
            'sender_id'  => auth()->id(),
            'receiver_id'=> $receiverId,
            'type'       => 'text',
            'message'    => $request->message,
            'replied_to' => $request->replied_to,
            'is_read'    => false,
            'expires_at' => $expiresAt,
        ]);
    }

    // 📡 ATTACH USERS + BROADCAST
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

        broadcast(new \App\Events\NewMessage($message))->toOthers();
    }

    return response()->json([
        'messages' => $messages
    ]);
}


// public function send(Request $request)
// {
//     $request->validate([
//         'chat_id'    => 'required|exists:chats,id',
//         'type'       => 'nullable|in:text,image,voice,video,file,audio',
//         'types'      => 'nullable|array',
//         'types.*'    => 'in:image,video,voice,file,audio',
//         'message'    => 'nullable|string',
//         'file'       => 'nullable|file|max:20480',
//         'files'      => 'nullable|array',
//         'files.*'    => 'file|max:20480',
//         'replied_to' => 'nullable|exists:messages,id',
//     ]);

//     $chat = Chat::findOrFail($request->chat_id);

//     if ($chat->isBlockedFor(auth()->id())) {
//         return response()->json(['message' => 'You are blocked in this chat'], 403);
//     }

//     $receiverId = null;

//     if ($chat->teacher_id && $chat->student_id) {
//         $receiverId = $chat->teacher_id == auth()->id()
//             ? $chat->student_id
//             : $chat->teacher_id;
//     }

//     if ($chat->user_one_id && $chat->user_two_id) {
//         $receiverId = $chat->user_one_id == auth()->id()
//             ? $chat->user_two_id
//             : $chat->user_one_id;
//     }

//     $expiresAt = null;
//     if ($chat->disappearing_mode && $chat->disappearing_time > 0) {
//         $expiresAt = now()->addSeconds($chat->disappearing_time);
//     }

//     $messages = [];

//     /**
//      * Helper: clean filename (WhatsApp-style safe)
//      */
//     $generateFileName = function ($file) {
//         $original = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
//         $extension = $file->getClientOriginalExtension();

//         // clean spaces + special chars
//         $clean = preg_replace('/[^A-Za-z0-9_\-]/', '_', $original);

//         return $clean . '_' . time() . '.' . $extension;
//     };

//     // 📦 MULTIPLE FILES
//     if ($request->hasFile('files')) {

//         $files = $request->file('files');
//         $types = $request->types ?? [];

//         foreach ($files as $index => $file) {

//             $storedName = $generateFileName($file);

//             $path = $file->storeAs('chat_files', $storedName, 'public');

//             $messages[] = Message::create([
//                 'chat_id'    => $chat->id,
//                 'sender_id'  => auth()->id(),
//                 'receiver_id'=> $receiverId,
//                 'type'       => $types[$index] ?? 'file',
//                 'message'    => $request->message,
//                 'file'       => $path,
//                 'file_name'  => $file->getClientOriginalName(), // 👈 ORIGINAL NAME (DISPLAY)
//                 'replied_to' => $request->replied_to,
//                 'is_read'    => false,
//                 'expires_at' => $expiresAt,
//             ]);
//         }
//     }

//     // 📄 SINGLE FILE
//     elseif ($request->hasFile('file')) {

//         $file = $request->file('file');

//         $path = $file->store('chat_files', 'public');

//         $fileName = $file->getClientOriginalName();

//         $message = Message::create([
//             'chat_id'    => $chat->id,
//             'sender_id'  => auth()->id(),
//             'receiver_id'=> $receiverId,
//             'type'       => $request->type,
//             'message'    => $request->message,
//             'file'       => $path,
//             'file_name'  => $fileName, // 👈 THIS WILL NOW WORK
//             'replied_to' => $request->replied_to,
//             'is_read'    => false,
//             'expires_at' => $expiresAt,
//         ]);
//     }

//     // 💬 TEXT MESSAGE
//     else {

//         $messages[] = Message::create([
//             'chat_id'    => $chat->id,
//             'sender_id'  => auth()->id(),
//             'receiver_id'=> $receiverId,
//             'type'       => 'text',
//             'message'    => $request->message,
//             'replied_to' => $request->replied_to,
//             'is_read'    => false,
//             'expires_at' => $expiresAt,
//         ]);
//     }

//     // 📡 ATTACH + BROADCAST
//     foreach ($messages as $message) {

//         $userIds = collect([
//             $chat->teacher_id,
//             $chat->student_id,
//             $chat->user_one_id,
//             $chat->user_two_id
//         ])->filter()->unique();

//         foreach ($userIds as $userId) {
//             $message->users()->attach($userId, ['deleted' => false]);
//         }

//         $message->load(['sender', 'repliedMessage.sender']);

//         broadcast(new NewMessage($message))->toOthers();
//     }

//     return response()->json([
//         'messages' => $messages
//     ]);
// }


public function sendR(Request $request)
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
    ]);

    $chat = Chat::findOrFail($request->chat_id);

    if ($chat->isBlockedFor(auth()->id())) {
        return response()->json(['message' => 'You are blocked in this chat'], 403);
    }

    // 🔥 DETERMINE RECEIVER
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

    // 🔥 TRIM DATA
    $starts = $request->trim_start ?? [];
    $ends   = $request->trim_end ?? [];
    $types  = $request->types ?? [];

    // =====================================================
    // 📦 MULTIPLE FILES
    // =====================================================
    if ($request->hasFile('files')) {

        $files = $request->file('files');

        foreach ($files as $index => $file) {

            $storedName = $generateFileName($file);
            $type = $types[$index] ?? 'file';

            $start = $starts[$index] ?? 0;
            $end   = $ends[$index] ?? 0;

            $path = null;

            // 🎬 VIDEO TRIM
            if ($type === 'video' && $end > $start) {

                $tempPath = $file->getRealPath();

                $outputName = 'trimmed_' . $storedName;
                $outputFullPath = storage_path('app/public/chat_files/' . $outputName);

                // 🔥 CREATE DIRECTORY IF NOT EXISTS
                if (!file_exists(dirname($outputFullPath))) {
                    mkdir(dirname($outputFullPath), 0777, true);
                }

                // 🔥 FFMPEG COMMAND
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
    }

    // =====================================================
    // 📄 SINGLE FILE
    // =====================================================
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

    // =====================================================
    // 💬 TEXT MESSAGE
    // =====================================================
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

    // =====================================================
    // 📡 BROADCAST
    // =====================================================
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

    return response()->json([
        'messages' => $messages
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
                'type' => 'private', 
            ]);
        }


        // Forward messages
        foreach ($messageIds as $id) {
            $originalMessage = Message::findOrFail($id);

            Message::create([
                'chat_id' => $chat->id,
                'sender_id' => $sender->id,
                'user_id' => auth()->id(),
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

}
//unreadSendersCount