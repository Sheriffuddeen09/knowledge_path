<?php

namespace App\Http\Controllers;

use App\Models\CommunityMessage;
use App\Models\CommunityPoll;
use App\Models\CommunityPollOption;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\CommunityPollVote;
use Illuminate\Support\Facades\Auth;


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

            logger()->info('Poll Created', $poll->toArray());

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
                'message' => $poll->question

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

   
    public function vote(Request $request)
    {
        $request->validate([
            'poll_id' => 'required|exists:community_polls,id',
            'option_ids' => 'required|array|min:1',
            'option_ids.*' => 'exists:community_poll_options,id',
        ]);

        $poll = CommunityPoll::with('options')->findOrFail(
            $request->poll_id
        );

        if (
            $poll->expires_at &&
            now()->greaterThan($poll->expires_at)
        ) {
            return response()->json([
                'success' => false,
                'message' => 'This poll has ended.'
            ], 422);
        }

        if (
            !$poll->multiple_choice &&
            count($request->option_ids) > 1
        ) {
            return response()->json([
                'success' => false,
                'message' => 'Only one option can be selected.'
            ], 422);
        }

        $userId = Auth::id();

        $alreadyVoted = CommunityPollVote::where(
            'poll_id',
            $poll->id
        )
        ->where(
            'user_id',
            $userId
        )
        ->exists();

        if ($alreadyVoted) {

            return response()->json([
                'success' => false,
                'message' => 'You have already voted.'
            ], 422);

        }

        DB::beginTransaction();

        try {

            foreach ($request->option_ids as $optionId) {

                CommunityPollVote::create([

                    'poll_id'   => $poll->id,

                    'option_id' => $optionId,

                    'user_id'   => $userId,

                ]);

                CommunityPollOption::where(
                    'id',
                    $optionId
                )->increment('votes');

            }

            DB::commit();

            $poll = CommunityPoll::with([
                'sender',
                'options.voteUsers'
            ])->find($poll->id);

            return response()->json([

                'success' => true,

                'poll' => $this->formatPoll($poll),

            ]);

        } catch (\Exception $e) {

            DB::rollBack();

            return response()->json([

                'success' => false,

                'message' => $e->getMessage()

            ], 500);

        }
    }

    public function show(CommunityPoll $poll)
    {
        $poll->load([
            'sender',
            'options.voteUsers'
        ]);

        return response()->json([

            'success' => true,

            'poll' => $this->formatPoll($poll),

        ]);
    }

   
    private function formatPoll($poll)
    {
        $totalVotes = $poll->options->sum('votes');

        return [

            'id' => $poll->id,

            'question' => $poll->question,

            'multiple_choice' => $poll->multiple_choice,

            'expires_at' => $poll->expires_at,

            'total_votes' => $totalVotes,

            'options' => $poll->options
                ->map(function ($option) use ($totalVotes) {

                    return [

                        'id' => $option->id,

                        'option' => $option->option,

                        'votes' => $option->votes,

                        'percentage' => $totalVotes > 0
                            ? round(
                                ($option->votes / $totalVotes) * 100
                            )
                            : 0,

                        'user_voted' => $option
                            ->voteUsers
                            ->contains(
                                'user_id',
                                auth()->id()
                            ),

                        'voters' => $option
                            ->voteUsers
                            ->map(function ($vote) {

                                return [

                                    'id' => $vote->user->id,

                                    'first_name' => $vote->user->first_name,

                                    'last_name' => $vote->user->last_name,

                                    'image' => $vote->user->image,

                                ];

                            })
                            ->values(),

                    ];

                })
                ->values(),

        ];
    }
}