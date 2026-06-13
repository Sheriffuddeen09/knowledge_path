<?php

namespace App\Http\Controllers;
use App\Models\Community;
use App\Models\User;
use App\Models\Chat;
use App\Models\CommunityMember;
use Illuminate\Http\Request;
use App\Models\CommunityMessage;
use App\Models\Message;
use App\Events\NewCommunityMessage;
use App\Models\CommunityMessageReaction;
use App\Models\CommunityPendingResponse;
use App\Models\CommunityMessageApproval;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;


class CommunityController extends Controller

{


     public function messages($id)
{
    $community = Community::with([
        'messages.sender',
        'messages.repliedMessage.sender',
        'messages.approvals'
    ])->findOrFail($id);

    $lastReadId = DB::table(
        'community_members'
    )
    ->where(
        'community_id',
        $id
    )
    ->where(
        'user_id',
        auth()->id()
    )
    ->value(
        'last_read_message_id'
    );

    $firstUnreadMessageId = null;

    $messages = $community
        ->messages
        ->sortBy('id')
        ->map(function ($msg)
        use (
            &$firstUnreadMessageId,
            $lastReadId
        ) {
            if (
                $lastReadId &&
                !$firstUnreadMessageId &&
                $msg->id > $lastReadId
            ) {

                $firstUnreadMessageId =
                    $msg->id;
            }
            if ($msg->file) {

                $msg->files = [[

                    'file_url' => asset(
                        'storage/' .
                        $msg->file
                    ),

                    'file_name' => basename(
                        $msg->file
                    ),

                    'type' =>
                        $msg->type,
                ]];

            } else {

                $msg->files = [];
            }
            if (
                $msg->repliedMessage
            ) {

                $msg->replied_message = [

                    'id' =>
                        $msg
                        ->repliedMessage
                        ->id,

                    'message' =>
                        $msg
                        ->repliedMessage
                        ->message,

                    'sender' =>
                        $msg
                        ->repliedMessage
                        ->sender,
                ];
            }
            $msg->approval_count =
                $msg
                ->approvals
                ->count();
            return $msg;
        })
        ->values();

        return response()->json([
        'messages' =>
            $messages,
        'first_unread_message_id' =>
            $firstUnreadMessageId,
    ]);
}


public function index()
{
    $userId = auth()->id();

    $communities = Community::with([
        'members' => function ($query) {
            $query->wherePivot(
                'membership_status',
                'approved'
            );

        },
        'lastMessage.sender'
    ])
    ->whereHas('members', function ($q) use ($userId) {
    $q->where('community_members.user_id', $userId)
      ->where('community_members.membership_status', 'approved');
    })
    ->latest()
    ->get()
    ->map(function ($community) use ($userId) {

        $member = $community
            ->members
            ->first(function ($member) use ($userId) {

                return $member->id == $userId
                    && $member->pivot->membership_status === 'approved';
            });

        $community->my_role =
            $member?->pivot?->role;

        $community->membership_status =
            $member?->pivot?->membership_status;

        $lastReadMessageId =
            $member?->pivot?->last_read_message_id ?? 0;

        $community->unread_count =
            CommunityMessage::where(
                'community_id',
                $community->id
            )
            ->where(
                'id',
                '>',
                $lastReadMessageId
            )
            ->where(
                'sender_id',
                '!=',
                $userId
            )
            ->count();
        return $community;
    });

    return response()->json([
        'communities' => $communities,
    ]);
}


public function markAsRead($communityId)
{
    $lastMessageId = CommunityMessage::where(
        'community_id',
        $communityId
    )->max('id');

    $updated = CommunityMember::where(
        'community_id',
        $communityId
    )
    ->where(
        'user_id',
        auth()->id()
    )
    ->update([
        'last_read_message_id' => $lastMessageId,
    ]);

    return response()->json([
        'success' => true,
        'updated' => $updated,
        'last_message_id' => $lastMessageId,
        'user_id' => auth()->id(),
        'community_id' => $communityId,
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


    public function pin(Request $request)
    {
        $request->validate([
            'message_id' => 'required|exists:community_messages,id',
        ]);

        $message = CommunityMessage::findOrFail(
            $request->message_id
        );

        $message->update([
            'is_pinned' => true,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Message pinned successfully',
            'data' => $message,
        ]);
    }

    public function unpin(Request $request)
    {
        $request->validate([
            'message_id' => 'required|exists:community_messages,id',
        ]);

        $message = CommunityMessage::findOrFail(
            $request->message_id
        );

        $message->update([
            'is_pinned' => false,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Message unpinned successfully',
            'data' => $message,
        ]);
    }

    
    public function forward(Request $request)
{
    $request->validate([
        'message_ids' => 'required|array|min:1',
        'message_ids.*' => 'exists:community_messages,id',

        'targets' => 'required|array|min:1',
        'targets.*.id' => 'required|integer',
        'targets.*.type' => 'required|in:user,group',
    ]);

    $authId = auth()->id();

    $communityMessages = CommunityMessage::with([
        'sender',
        'community',
        'approvals',
    ])
    ->whereIn('id', $request->message_ids)
    ->get();

    $createdMessages = [];
    $lastChat = null;
    $lastForwardedMessageId = null;

    foreach ($request->targets as $target) {
        if ($target['type'] === 'user') {

            $pair = [
                'user_one_id' => min(
                    $authId,
                    $target['id']
                ),
                'user_two_id' => max(
                    $authId,
                    $target['id']
                ),
            ];

            $chat = Chat::where(
                'user_one_id',
                $pair['user_one_id']
            )
            ->where(
                'user_two_id',
                $pair['user_two_id']
            )
            ->first();

            if (!$chat) {
                $chat = Chat::create([
                    'user_one_id' => $pair['user_one_id'],
                    'user_two_id' => $pair['user_two_id'],
                    'type' => 'private',
                ]);
            }

            foreach ($communityMessages as $original) {

                $messageText = $original->message;

                if (
                    $original->approvals &&
                    $original->approvals->count()
                ) {
                    $latestApproval =
                        $original->approvals->last();

                    $messageText =
                        $latestApproval->admin_response
                        ?: $original->message;
                }

                $message = Message::create([
                    'chat_id' => $chat->id,
                    'sender_id' => $authId,

                    'message' => $messageText,
                    'type' => $original->type,
                    'file' => $original->file,

                    'is_forwarded' => true,
                    'forwarded_from' => $original->id,

                    'forward_source' => 'community',

                    'forward_source_name' =>
                        $original->community?->community_name,

                    'forward_source_image' =>
                        $original->community?->community_image,
                    'forward_source_message_id' =>
                        $original->id,

                    'forward_source_community_id' =>
                        $original->community_id,
                ]);

                $message->load('sender');

                $lastForwardedMessageId =
                    $message->id;

                $createdMessages[] = $message;
            }

            $lastChat = $chat;
        }

        if ($target['type'] === 'group') {

            $chat = Chat::where('id', $target['id'])
                ->where('type', 'group')
                ->first();

            if (!$chat) {
                continue;
            }

            foreach ($communityMessages as $original) {

                $messageText = $original->message;

                if (
                    $original->approvals &&
                    $original->approvals->count()
                ) {
                    $latestApproval =
                        $original->approvals->last();

                    $messageText =
                        $latestApproval->admin_response
                        ?: $original->message;
                }

                $message = Message::create([
                    'chat_id' => $chat->id,
                    'sender_id' => $authId,

                    'message' => $messageText,
                    'type' => $original->type,
                    'file' => $original->file,

                    'is_forwarded' => true,
                    'forwarded_from' => $original->id,

                    'forward_source' => 'community',

                    'forward_source_name' =>
                        $original->community?->community_name,

                    'forward_source_image' =>
                        $original->community?->community_image,
                    'forward_source_message_id' =>
                        $original->id,

                    'forward_source_community_id' =>
                        $original->community_id,
                ]);

                $message->load('sender');

                $lastForwardedMessageId =
                    $message->id;

                $createdMessages[] = $message;

              
            }

            $lastChat = $chat;
        }
    }

    return response()->json([
        'success' => true,
        'chat_id' => $lastChat?->id,
        'chat_type' => $lastChat?->type,
        'message_id' => $lastForwardedMessageId,
        'forwarded_count' => count($request->targets),
        'messages' => $createdMessages,
    ]);
}

    private function getPrivateCommunity($userA, $userB)
    {
    $pair = [
        'min' => min($userA, $userB),
        'max' => max($userA, $userB),
    ];

    $community = Community::where('type', 'private')
        ->whereHas('members', function ($q) use ($pair) {
            $q->where('user_id', $pair['min']);
        })
        ->whereHas('members', function ($q) use ($pair) {
            $q->where('user_id', $pair['max']);
        })
        ->first();

    if ($community) {
        return $community->id;
    }

    $community = Community::create([
        'creator_id' => $userA,
        'owner_id' => $userA,
        'community_name' => 'Private Chat',
    ]);

    CommunityMember::insert([
        [
            'community_id' => $community->id,
            'user_id' => $pair['min']
        ],
        [
            'community_id' => $community->id,
            'user_id' => $pair['max']
        ],
    ]);

    return $community->id;
}

    public function explore()
{
    $userId = auth()->id();

    $communities = Community::whereDoesntHave(
        'members',
        function ($query) use ($userId) {

            $query->where(
                'user_id',
                $userId
            )
            ->where(
                'membership_status',
                'approved'
            );

        }
    )
    ->withCount([
        'members as followers_count' => function ($query) {

            $query->where(
                'membership_status',
                'approved'
            );

        }
    ])
    ->get();

    return response()->json([
        'communities' => $communities,
    ]);
}


public function follow(Community $community)
{
    $user = auth()->user();

    $community->members()->syncWithoutDetaching([
        $user->id => [
            'membership_status' => 'approved',
            'joined_at' => now(),
        ]
    ]);

    return response()->json([
        'message' => 'Community followed successfully.',
    ]);
}


public function unfollow($id)
{
    $member = CommunityMember::where(
        'community_id',
        $id
    )
    ->where(
        'user_id',
        auth()->id()
    )
    ->where(
        'membership_status',
        'approved'
    )
    ->first();

    if (!$member) {
        return response()->json([
            'success' => false,
            'message' => 'You are not a member of this channel.',
        ], 404);
    }

    $member->update([
        'membership_status' => 'left',
        'last_read_message_id' => null,
    ]);

    return response()->json([
        'success' => true,
        'community_id' => $id,
        'message' => 'Channel left successfully.',
    ]);
}



public function hideCommunity($communityId)
{
    DB::table('community_members')
        ->where(
            'community_id',
            $communityId
        )
        ->where(
            'user_id',
            auth()->id()
        )
        ->update([
            'hidden_until' => now()
                ->addDays(30),
        ]);

    return response()->json([
        'success' => true,
    ]);
}
    

public function removeMember(
    Request $request,
    Community $community
)
{
    CommunityMember::where(
        'community_id',
        $community->id
    )
    ->where(
        'user_id',
        $request->user_id
    )
    ->update([
        'membership_status' => 'removed',
    ]);

    return response()->json([
        'success' => true,
    ]);
}



public function availableMembers($communityId)
{
    $userId = auth()->id();

    $communityMemberIds = DB::table('community_members')
        ->where('community_id', $communityId)
        ->whereIn('membership_status', ['approved'])
        ->pluck('user_id');

    $chatUserIds = Chat::where(function ($query) use ($userId) {

        $query->where('teacher_id', $userId)
            ->orWhere('student_id', $userId)
            ->orWhere('user_one_id', $userId)
            ->orWhere('user_two_id', $userId);

    })
    ->get()
    ->flatMap(function ($chat) use ($userId) {

        $ids = [];

        if (
            $chat->user_one_id &&
            $chat->user_two_id
        ) {

            $ids[] =
                $chat->user_one_id == $userId
                    ? $chat->user_two_id
                    : $chat->user_one_id;
        }

        if (
            $chat->teacher_id &&
            $chat->teacher_id != $userId
        ) {

            $ids[] = $chat->teacher_id;
        }

        if (
            $chat->student_id &&
            $chat->student_id != $userId
        ) {

            $ids[] = $chat->student_id;
        }

        return $ids;

    })
    ->unique()
    ->values();

    $users = User::whereIn(
            'id',
            $chatUserIds
        )
        ->whereNotIn(
            'id',
            $communityMemberIds
        )
        ->get([
            'id',
            'first_name',
            'last_name',
            'profile_photo',
        ]);

    return response()->json([
        'users' => $users,
    ]);
}


public function addMember(
    Request $request,
    Community $community
) {
    $request->validate([
        'user_id' => [
            'required',
            'exists:users,id',
        ],
    ]);

    $userId = $request->user_id;

    Log::info('Add member request received', [
        'community_id' => $community->id,
        'user_id' => $userId,
    ]);

    $existingMember = CommunityMember::where(
        'community_id',
        $community->id
    )
    ->where(
        'user_id',
        $userId
    )
    ->first();

    Log::info('Existing member lookup', [
        'found' => (bool) $existingMember,
        'membership_status' => $existingMember?->membership_status,
        'member_id' => $existingMember?->id,
    ]);

    if (
        $existingMember &&
        $existingMember->membership_status === 'approved'
    ) {

        Log::info('User already approved member', [
            'community_id' => $community->id,
            'user_id' => $userId,
        ]);

        return response()->json([
            'success' => false,
            'message' => 'User is already a member.',
        ], 422);
    }

    if ($existingMember) {

        Log::info('Updating existing member', [
            'before_status' => $existingMember->membership_status,
        ]);

        $existingMember->membership_status = 'approved';
        $existingMember->joined_at = now();

        Log::info('Dirty attributes', [
            'dirty' => $existingMember->getDirty(),
        ]);

        $result = $existingMember->save();

        Log::info('Save result', [
            'saved' => $result,
        ]);

        $existingMember->refresh();

        Log::info('After save', [
            'membership_status' => $existingMember->membership_status,
            'joined_at' => $existingMember->joined_at,
        ]);

        $existingMember->refresh();

        Log::info('Existing member updated', [
            'after_status' => $existingMember->membership_status,
            'joined_at' => $existingMember->joined_at,
        ]);

    } else {

        Log::info('Creating new member');

        $memberRecord = CommunityMember::create([
            'community_id' => $community->id,
            'user_id' => $userId,
            'role' => 'member',
            'membership_status' => 'approved',
            'joined_at' => now(),
            'can_message' => true,
            'muted' => false,
        ]);

        Log::info('New member created', [
            'community_member_id' => $memberRecord->id,
        ]);
    }

    Log::info('Final database state', [
        'community_member' => CommunityMember::where(
            'community_id',
            $community->id
        )
        ->where(
            'user_id',
            $userId
        )
        ->first()
        ?->toArray(),
    ]);

    $member = User::select(
        'id',
        'first_name',
        'last_name',
        'profile_photo'
    )->find($userId);

    return response()->json([
        'success' => true,
        'message' => 'Member added successfully.',
        'member' => $member,
    ]);
}

}
