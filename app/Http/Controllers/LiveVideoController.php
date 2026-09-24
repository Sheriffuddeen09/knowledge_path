<?php

namespace App\Http\Controllers;

use App\Models\Post;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Agence104\LiveKit\AccessToken;
use Agence104\LiveKit\AccessTokenOptions;
use Agence104\LiveKit\VideoGrant;

class LiveVideoController extends Controller
{
    /**
     * Start a live video.
     */
    public function start(Request $request)
    {
        $request->validate([
            'content' => 'nullable|string|max:700',
        ]);

        $user = $request->user();

        /*
        |--------------------------------------------------------------------------
        | Prevent multiple live videos from the same user
        |--------------------------------------------------------------------------
        */

        $existingLive = Post::where('user_id', $user->id)
            ->where('is_live', true)
            ->where('live_status', 'live')
            ->first();

        if ($existingLive) {
            return response()->json([
                'message' => 'You already have a live video running.',
                'post' => $existingLive,
            ], 422);
        }

        /*
        |--------------------------------------------------------------------------
        | Create unique LiveKit room
        |--------------------------------------------------------------------------
        */

        $roomName = 'live_' . $user->id . '_' . Str::uuid();

        /*
        |--------------------------------------------------------------------------
        | Create live post
        |--------------------------------------------------------------------------
        */

        $post = Post::create([
            'user_id' => $user->id,

            // Use the normal Post content field
            'content' => $request->content,

            'post_type' => 'post',

            'is_live' => true,
            'live_status' => 'live',
            'live_room_name' => $roomName,
            'live_started_at' => now(),
            'live_viewers_count' => 0,
        ]);

        /*
        |--------------------------------------------------------------------------
        | Create broadcaster token
        |--------------------------------------------------------------------------
        */

        $identity = 'user_' . $user->id . '_' . Str::random(8);

        $tokenOptions = (new AccessTokenOptions())
            ->setIdentity($identity);

        $videoGrant = (new VideoGrant())
            ->setRoomJoin()
            ->setRoomName($roomName)
            ->setCanPublish()
            ->setCanSubscribe();

        $token = (new AccessToken(
            config('services.livekit.api_key'),
            config('services.livekit.api_secret')
        ))
            ->init($tokenOptions)
            ->setGrant($videoGrant)
            ->toJwt();

        return response()->json([
            'success' => true,

            'message' => 'Live video started.',

            'post' => $post->load([
                'user:id,first_name,last_name,image',
            ]),

            'live' => [
                'room_name' => $roomName,
                'token' => $token,
                'server_url' => config('services.livekit.url'),
            ],
        ], 201);
    }

    /**
     * Get a viewer token.
     */
    public function join(Request $request, Post $post)
    {
        if (
            !$post->is_live ||
            $post->live_status !== 'live'
        ) {
            return response()->json([
                'message' => 'This live video is no longer active.'
            ], 422);
        }

        $user = $request->user();

        /*
        |--------------------------------------------------------------------------
        | Viewer identity
        |--------------------------------------------------------------------------
        */

        $identity = 'viewer_' . $user->id . '_' . Str::random(8);

        $tokenOptions = (new AccessTokenOptions())
            ->setIdentity($identity);

        /*
        |--------------------------------------------------------------------------
        | Viewer can subscribe but cannot publish
        |--------------------------------------------------------------------------
        */

        $videoGrant = (new VideoGrant())
            ->setRoomJoin()
            ->setRoomName($post->live_room_name)
            ->setCanPublish(false)
            ->setCanSubscribe();

        $token = (new AccessToken(
            config('services.livekit.api_key'),
            config('services.livekit.api_secret')
        ))
            ->init($tokenOptions)
            ->setGrant($videoGrant)
            ->toJwt();

        /*
        |--------------------------------------------------------------------------
        | Increment viewer count
        |--------------------------------------------------------------------------
        */

        $post->increment('live_viewers_count');

        return response()->json([
            'success' => true,

            'live' => [
                'room_name' => $post->live_room_name,
                'token' => $token,
                'server_url' => config('services.livekit.url'),
            ],
        ]);
    }

    /**
     * End live video.
     */
    public function end(Request $request, Post $post)
    {
        $user = $request->user();

        if ((int) $post->user_id !== (int) $user->id) {
            return response()->json([
                'message' => 'You cannot end this live video.'
            ], 403);
        }

        if (
            !$post->is_live ||
            $post->live_status !== 'live'
        ) {
            return response()->json([
                'message' => 'This live video is not active.'
            ], 422);
        }

        $post->update([
            'is_live' => false,
            'live_status' => 'ended',
            'live_ended_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Live video ended.',
            'post' => $post->fresh([
                'user:id,first_name,last_name,image',
                'media',
            ]),
        ]);
    }
}