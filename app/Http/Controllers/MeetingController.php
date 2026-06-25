<?php

namespace App\Http\Controllers;

use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Models\MeetingLink;
use App\Models\Chat;
use App\Models\Message;

class MeetingController extends Controller
{
    public function create(Request $request)
    {
        $request->validate([
            'call_type' => 'required',
            'expires_at' => 'required'
        ]);

        $meeting = MeetingLink::create([
            'room_id' => Str::uuid(),
            'creator_id' => auth()->id(),
            'call_type' => $request->call_type,
            'expires_at' => $request->expires_at
        ]);

        return response()->json([
            'success' => true,
            'meeting' => $meeting
        ]);
    }

    public function show($roomId)
    {
        $meeting = MeetingLink::where(
            'room_id',
            $roomId
        )->firstOrFail();

        return response()->json([
            'meeting' => $meeting
        ]);
    }

    public function sendMeetingInvite(Request $request)
{
    $request->validate([
        'meeting' => 'required|array',

        'targets' => 'required|array|min:1',
        'targets.*.id' => 'required|integer',
        'targets.*.type' => 'required|in:user,group',
    ]);

    $authId = auth()->id();

    $meeting = $request->meeting;

    $createdMessages = [];

    $lastChat = null;

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
                    'user_one_id' =>
                        $pair['user_one_id'],

                    'user_two_id' =>
                        $pair['user_two_id'],

                    'type' => 'private',
                ]);
            }

            $message = Message::create([
                'chat_id' => $chat->id,
                'sender_id' => $authId,

                'type' => 'meeting_invite',

                'message' =>
                    $meeting['call_type'] === 'video'
                    ? '📹 Video Meeting Link'
                    : '📞 Audio Meeting Link',

                'meeting_room_id' =>
                    $meeting['room_id'],

                'meeting_call_type' =>
                    $meeting['call_type'],

                'meeting_expires_at' =>
                    $meeting['expires_at'],
                'meeting_link' => config('app.frontend_url')
                    . '/meeting/' . $meeting['room_id'],
            ]);

            $message->load('sender');

            $createdMessages[] = $message;

            $lastChat = $chat;
        }

        if ($target['type'] === 'group') {

            $chat = Chat::where(
                'id',
                $target['id']
            )
            ->where(
                'type',
                'group'
            )
            ->first();

            if (!$chat) {
                continue;
            }

            $message = Message::create([
                'chat_id' => $chat->id,
                'sender_id' => $authId,

                'type' => 'meeting_invite',

                'message' =>
                    $meeting['call_type'] === 'video'
                    ? '📹 Video Meeting Link'
                    : '📞 Audio Meeting Link',

                'meeting_room_id' =>
                    $meeting['room_id'],

                'meeting_call_type' =>
                    $meeting['call_type'],

                'meeting_expires_at' =>
                    $meeting['expires_at'],
                'meeting_link' => config('app.frontend_url')
                    . '/meeting/' . $meeting['room_id'],
            ]);

            $message->load('sender');

            $createdMessages[] = $message;

            $lastChat = $chat;
        }
    }

    return response()->json([
        'success' => true,

        'chat_id' =>
            $lastChat?->id,

        'chat_type' =>
            $lastChat?->type,

        'forwarded_count' =>
            count($request->targets),

        'messages' =>
            $createdMessages,
    ]);
}


}