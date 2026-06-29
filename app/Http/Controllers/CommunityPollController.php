<?php

namespace App\Http\Controllers;

use App\Models\CommunityMessage;
use App\Models\CommunityPoll;
use App\Models\CommunityPollOption;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CommunityPollController extends Controller
{
    public function create(Request $request)
    {
        $request->validate([

            'community_id' => 'required|exists:communities,id',

            'question' => 'required|string|max:255',

            'options' => 'required|array|min:2|max:10',

            'options.*' => 'required|string|max:255',

            'multiple_choice' => 'boolean',

            'expires_at' => 'nullable|date'

        ]);

        DB::beginTransaction();

        try {

            $poll = CommunityPoll::create([

                'community_id' => $request->community_id,

                'sender_id' => auth()->id(),

                'question' => $request->question,

                'multiple_choice' => $request->multiple_choice,

                'expires_at' => $request->expires_at

            ]);

            foreach ($request->options as $option) {

                CommunityPollOption::create([

                    'poll_id' => $poll->id,

                    'option' => $option

                ]);

            }

            $message = CommunityMessage::create([

                'community_id' => $request->community_id,

                'sender_id' => auth()->id(),

                'type' => 'poll',

                'poll_id' => $poll->id,

                'message' => null

            ]);

            DB::commit();

            return response()->json([

                'success' => true,

                'message' => $message->load(
                    'sender',
                    'poll.options'
                )

            ]);

        } catch (\Exception $e) {

            DB::rollBack();

            return response()->json([

                'success' => false,

                'message' => $e->getMessage()

            ],500);

        }
    }
}