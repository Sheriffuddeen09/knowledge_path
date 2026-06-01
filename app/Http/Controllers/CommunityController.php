<?php

namespace App\Http\Controllers;
use App\Models\Community;
use App\Models\Chat;
use App\Models\CommunityMember;
use Illuminate\Http\Request;
use App\Models\CommunityMessage;
use App\Events\NewCommunityMessage;
use App\Models\CommunityMessageReaction;
use App\Models\CommunityPendingResponse;
use App\Models\CommunityMessageApproval;
use Illuminate\Support\Facades\Storage;

class CommunityController extends Controller

{


       public function messages($id)
        {
            $community = Community::with([
            'messages.sender',
            'messages.repliedMessage.sender',
            'messages.approvals'
        ])->findOrFail($id);

       $messages = $community->messages->map(function ($msg) {

    if ($msg->file) {
        $msg->files = [[
            'file_url' => asset('storage/' . $msg->file),
            'file_name' => basename($msg->file),
            'type' => $msg->type,
        ]];
    } else {
        $msg->files = [];
    }

    if ($msg->sender) {
        $msg->sender = $msg->sender;
    }

    if ($msg->repliedMessage) {
        $msg->replied_message = [
            'id' => $msg->repliedMessage->id,
            'message' => $msg->repliedMessage->message,
            'sender' => $msg->repliedMessage->sender,
        ];
    }

    // 🔥 IMPORTANT: attach approvals manually
    $msg->approvals = $msg->approvals ?? [];

    $msg->approval_count = $msg->approvals->count();



    return $msg;
    });

    return response()->json([
        'messages' => $messages
    ]);
}

public function index()
{
    $userId = auth()->id();

    $communities = Community::with([
        'members',
        'lastMessage.sender'
    ])
    ->whereHas('members', function ($q) use ($userId) {

        $q->where('user_id', $userId);

    })
    ->latest()
    ->get()
    ->map(function ($community) use ($userId) {

        $member = $community
            ->members
            ->firstWhere('id', $userId);

        $community->my_role =
            $member?->pivot?->role;

        $community->membership_status =
            $member?->pivot?->membership_status;

        return $community;
    });

    return response()->json([
        'communities' => $communities
    ]);
}

public function create(Request $request)
{
    $request->validate([
        'community_name' => 'required|string|max:255',
        'community_description' => 'nullable|string',
        'community_image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:20480',
        'only_admin_can_message' => 'nullable|boolean',
        'users' => 'nullable|array',
        'users.*' => 'exists:users,id',
    ]);

    $imagePath = null;

    if ($request->hasFile('community_image')) {
        $imagePath = $request->file('community_image')
            ->store('community_images', 'public');
    }

    $community = Community::create([
        'creator_id' => auth()->id(),
        'owner_id' => auth()->id(),
        'community_name' => $request->community_name,
        'community_description' => $request->community_description,
        'community_image' => $imagePath,
        'only_admin_can_message' => $request->only_admin_can_message ?? true,
    ]);

    // ✅ OWNER
    $community->members()->attach(auth()->id(), [
        'role' => 'owner',
        'can_message' => true,
        'muted' => false,
        'joined_at' => now(),
    ]);

    // ✅ ADD USERS
    if ($request->users) {
        foreach ($request->users as $userId) {

            if ($userId != auth()->id()) {
                $community->members()->attach($userId, [
                    'role' => 'member',
                    'can_message' => true,
                    'muted' => false,
                    'joined_at' => now(),
                ]);
            }
        }
    }

    return response()->json([
        'message' => 'Community created',
        'community' => $community->load('members')
    ]);
}


public function sendCommunityMessage(Request $request)
{
    $request->validate([
        'action' => 'required|in:send,edit,delete,clear',
        'community_id' => 'required|exists:communities,id',
        'message_id' => 'nullable|exists:community_messages,id',
        'type' => 'nullable|in:text,image,voice,video,file,audio',
        'message' => 'nullable|string',
        'file' => 'nullable|file|max:20480',
        'replied_to' => 'nullable|exists:community_messages,id',
        'response_mode' => 'nullable|boolean',
    ]);
    $community = Community::findOrFail(
        $request->community_id
    );
    $member = CommunityMember::where([
        'community_id' => $community->id,
        'user_id' => auth()->id(),
    ])->first();
    if (!$member) {
        return response()->json([
            'message' => 'Not a member'
        ], 403);
    }
    if ($request->action === 'delete') {
        $message = CommunityMessage::findOrFail(
            $request->message_id
        );
        if (
            $message->sender_id !== auth()->id()
        ) {
            return response()->json([
                'message' => 'Unauthorized'
            ], 403);
        }
        if (
            $message->file &&
            Storage::disk('public')->exists(
                $message->file
            )
        ) {
            Storage::disk('public')->delete(
                $message->file
            );
        }
        $message->deleted_at = now();
        $message->save();
        return response()->json([
            'success' => true,
            'action' => 'delete',
            'message_id' => $message->id,
            'deleted_at' => $message->deleted_at,
        ]);
    }
    if ($request->action === 'clear') {
        $message = CommunityMessage::findOrFail(
            $request->message_id
        );
        if (
            $message->sender_id !== auth()->id()
        ) {
            return response()->json([
                'message' => 'Unauthorized'
            ], 403);
        }
        if (
            $message->file &&
            Storage::disk('public')->exists(
                $message->file
            )
        ) {
            Storage::disk('public')->delete(
                $message->file
            );
        }
        $message->update([
            'message' => null,
            'file' => null,
            'type' => 'text',
        ]);
        return response()->json([
            'success' => true,
            'action' => 'clear',
            'message' => $message,
        ]);
    }
    if ($request->action === 'edit') {
        $message = CommunityMessage::findOrFail(
            $request->message_id
        );
        if (
            $message->sender_id !== auth()->id()
        ) {
            return response()->json([
                'message' => 'Unauthorized'
            ], 403);
        }
        $message->update([
            'message' => $request->message,
            'edited' => true,
        ]);
        $message->load([
            'sender',
            'community',
            'repliedMessage.sender'
        ]);
        return response()->json([
            'success' => true,
            'action' => 'edit',
            'message' => $message,
        ]);
    }
    if (
        $community->only_admin_can_message &&
        !in_array($member->role, [
            'owner',
            'admin'
        ])
    ) {
        return response()->json([
            'message' =>
                'Only admins can send messages'
        ], 403);
    }
    $mode = $community->disappearing_mode;
    $expiresAt = match ($mode) {
        '24h' => now()->addHours(24),
        '7d' => now()->addDays(7),
        '90d' => now()->addDays(90),
        default => null,
    };
    $generateFileName = function ($file) {
        $original = pathinfo(
            $file->getClientOriginalName(),
            PATHINFO_FILENAME
        );
        $extension =
            $file->getClientOriginalExtension();
        $clean = preg_replace(
            '/[^A-Za-z0-9_\-]/',
            '_',
            $original
        );
        return $clean .
            '_' .
            time() .
            '.' .
            $extension;
    };
    $data = [
        'community_id' => $community->id,
        'sender_id' => auth()->id(),
        'type' => $request->type ?? 'text',
        'message' => $request->message,
        'replied_to' => $request->replied_to,
        'expires_at' => $expiresAt,
        'response_mode' =>
            $request->boolean(
                'response_mode'
            ),
    ];
    if ($request->hasFile('file')) {
        $file = $request->file('file');
        $storedName =
            $generateFileName($file);
        $path = $file->storeAs(
            'community_files',
            $storedName,
            'public'
        );
        $data['file'] = $path;
    }
    $message = CommunityMessage::create(
        $data
    );
    $message->load([
        'sender',
        'community',
        'repliedMessage.sender'
    ]);
    broadcast(
        new NewCommunityMessage($message)
    )->toOthers();
    return response()->json([
        'success' => true,
        'action' => 'send',
        'message' => $message,
    ]);
}

public function sendCommunityVoice(Request $request)
{
    $request->validate([

        'community_id' => 'required|exists:communities,id',

        'voice' => 'required|file|mimes:webm,mp3,wav,ogg',

        'message' => 'nullable|string',

        'replied_to' =>
            'nullable|exists:community_messages,id',

    ]);

    $community = Community::findOrFail(
        $request->community_id
    );

    // =====================================
    // CHECK MEMBER
    // =====================================

    $member = CommunityMember::where([
        'community_id' => $community->id,
        'user_id' => auth()->id(),
    ])->first();

    if (!$member) {

        return response()->json([
            'message' => 'Not a member of this community'
        ], 403);
    }

    // =====================================
    // ONLY ADMIN CAN MESSAGE
    // =====================================

    if (
        $community->only_admin_can_message &&
        !in_array($member->role, [
            'owner',
            'admin'
        ])
    ) {

        return response()->json([
            'message' =>
                'Only admins can send messages'
        ], 403);
    }

    // =====================================
    // DISAPPEARING MODE
    // =====================================

    $mode = $community->disappearing_mode;

    $expiresAt = match ($mode) {

        '24h' => now()->addHours(24),

        '7d' => now()->addDays(7),

        '90d' => now()->addDays(90),

        default => null,
    };

    // =====================================
    // STORE VOICE FILE
    // =====================================

    $path = $request
        ->file('voice')
        ->store('community_voices', 'public');

    // =====================================
    // CREATE MESSAGE
    // =====================================

    $message = CommunityMessage::create([

        'community_id' =>
            $community->id,

        'sender_id' =>
            auth()->id(),

        'type' => 'voice',

        'file' => $path,

        'message' =>
            $request->message,

        'replied_to' =>
            $request->replied_to,

        'expires_at' =>
            $expiresAt,

    ]);

    $message->load([
        'sender',
        'community',
        'replyTo.sender'
    ]);

    broadcast(
        new NewCommunityMessage($message)
    )->toOthers();

    return response()->json([

        'message' => $message

    ]);
}

  public function react(Request $request)
    {
        $request->validate([
            'message_id' => 'required|exists:community_messages,id',
            'emoji' => 'required|string|max:20',
        ]);

        $userId = auth()->id();

        $message = CommunityMessage::findOrFail(
            $request->message_id
        );

        // ✅ REMOVE IF SAME REACTION EXISTS
        $existing = CommunityMessageReaction::where(
            'community_message_id',
            $message->id
        )
        ->where('user_id', $userId)
        ->where('emoji', $request->emoji)
        ->first();

        if ($existing) {

            $existing->delete();

        } else {

            // ✅ ONE REACTION PER USER
            CommunityMessageReaction::where(
                'community_message_id',
                $message->id
            )
            ->where('user_id', $userId)
            ->delete();

            CommunityMessageReaction::create([
                'community_message_id' => $message->id,
                'user_id' => $userId,
                'emoji' => $request->emoji,
            ]);
        }

        $message->load([
            'sender',
            'reactions.user',
            'repliedTo.sender',
        ]);

        return response()->json($message);
    }

    
    public function approve(Request $request, $id)
{
    $request->validate([
        'text' => 'nullable|string|max:200',
    ]);

    $pending = CommunityPendingResponse::find($id);

    if (!$pending) {
        return response()->json([
            'message' => 'Pending message not found'
        ], 404);
    }

    $message = CommunityMessage::create([
        'community_id'    => $pending->community_id,
        'sender_id'       => $pending->sender_id,
        'message'         => $pending->message,
        'type'            => 'text',
        'replied_to'      => $pending->reply_to,
        'response_mode'   => true,
        'approval_status' => 'approved',
    ]);

    if ($request->filled('text')) {

        CommunityMessageApproval::create([
            'message_id'     => $message->id,
            'admin_id'       => auth()->id(),
            'admin_response' => $request->text,
            'status'         => 'approved',
        ]);
    }

    $message->load([
        'sender',
        'repliedMessage.sender',
        'approvals'
    ]);

    $pending->delete();

    return response()->json([
        'success' => true,
        'message' => $message,
    ]);
}

public function reject(Request $request, $id)
{
    $pending = CommunityPendingResponse::find($id);

    if (!$pending) {
        return response()->json([
            'message' => 'Pending message not found'
        ], 404);
    }

    $pending->delete();

    return response()->json([
        'success' => true,
    ]);
}

public function sendPending(Request $request)
{
    $request->validate([
        'community_id' => 'required',
        'message' => 'required|string|max:200',
        'reply_to' => 'nullable'
    ]);

    CommunityPendingResponse::create([
        'community_id' => $request->community_id,
        'sender_id' => auth()->id(),
        'message' => $request->message,
        'reply_to' => $request->reply_to,
        'status' => 'pending',
    ]);

    return response()->json([
        'success' => true,
    ]);
}

    public function pendingMessages($id)
        {
            $pending = CommunityPendingResponse::with([
                'sender',
                'originalMessage.sender'
            ])
            ->where('community_id', $id)
            ->where('status', 'pending')
            ->latest()
            ->get();

            // ADD THIS
            $pending = $pending->map(function ($msg) {

    if ($msg->originalMessage) {

        $original = $msg->originalMessage;

        if ($original->file) {
            $original->files = [[
                'file_url' => asset('storage/' . $original->file),
                'file_name' => basename($original->file),
                'type' => $original->type,
            ]];
        } else {
            $original->files = [];
        }

        $msg->original_message = $original;

    } else {

        $msg->original_message = null;
    }

    return $msg;
    });

    return response()->json([
        'pending' => $pending
    ]);
    }

    public function download($id)
    {
        $message = CommunityMessage::findOrFail($id);

        // only image/video
        if (
            !in_array($message->type, [
                'image',
                'video'
            ])
        ) {

            return response()->json([
                'message' => 'File not downloadable'
            ], 403);
        }

        // file exists
        if (
            !$message->file ||
            !Storage::disk('public')->exists(
                $message->file
            )
        ) {

            return response()->json([
                'message' => 'File not found'
            ], 404);
        }

        $path = Storage::disk('public')
            ->path($message->file);

        $extension = pathinfo(
            $path,
            PATHINFO_EXTENSION
        );

        $filename =
            'community_' .
            $message->id .
            '_' .
            time() .
            '.' .
            $extension;

        return response()->download(
            $path,
            $filename,
            [
                'Content-Type' =>
                    mime_content_type($path),
            ]
        );
    }
}
