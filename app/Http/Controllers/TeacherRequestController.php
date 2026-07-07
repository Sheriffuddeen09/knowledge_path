<?php

namespace App\Http\Controllers;

use App\Models\TeacherForm;
use App\Models\Proposal;
use App\Models\TeacherRequest;
use App\Models\UserBadge;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Chat;
use App\Models\Message;
use Illuminate\Support\Facades\Mail;
use App\Mail\TeacherRequestAccepted;
use App\Mail\TeacherProposalRequestMail;
use App\Models\Coursetitle;
use App\Mail\StudentCancelledRequestMail;

class TeacherRequestController extends Controller
{
    public function send($proposalId)
{
    $teacher = auth()->user();

    if ($teacher->role !== "admin") {
        return response()->json([
            "message" => "Only teachers can send requests."
        ], 403);
    }

    $badge = UserBadge::where('user_id', $teacher->id)->first();

    if (!$badge) {
        return response()->json([
            "message" => "Badge account not found."
        ], 404);
    }

    if ($badge->badges < 20) {
        return response()->json([
            "message" => "You need at least 20 badges to send a request.",
            "balance" => $badge->badges
        ], 403);
    }

    $proposal = Proposal::with('student')->findOrFail($proposalId);

    $teacherForm = TeacherForm::where(
        'user_id',
        $teacher->id
    )->first();

    if (!$teacherForm) {
        return response()->json([
            "message" => "Complete your teacher profile first."
        ], 404);
    }

    $existing = TeacherRequest::where(
        'proposal_id',
        $proposal->id
    )
    ->where(
        'teacher_id',
        $teacher->id
    )
    ->first();

    if ($existing) {

        if ($existing->status === 'pending') {
            return response()->json([
                "message" => "Request already pending."
            ], 409);
        }

        if ($existing->status === 'accepted') {
            return response()->json([
                "message" => "Student has already accepted this request."
            ], 409);
        }

        DB::transaction(function () use (
            $badge,
            $existing,
            $proposal
        ) {

            $badge->decrement('badges', 20);

            $existing->update([
                'status' => 'pending',
                'teacher_deleted' => false,
            ]);

            Mail::to($proposal->student->email)
                ->send(new TeacherProposalRequestMail($existing));
        });

        return response()->json([
            'message' => 'Request sent again.',
            'balance' => $badge->fresh()->badges,
            'request' => $existing->fresh()
        ]);
    }

    $request = DB::transaction(function () use (
        $badge,
        $proposal,
        $teacher,
        $teacherForm
    ) {

        $badge->decrement('badges', 20);

        $request = TeacherRequest::create([
            'proposal_id' => $proposal->id,
            'student_id' => $proposal->student_id,
            'teacher_id' => $teacher->id,
            'teacher_form_id' => $teacherForm->id,
            'status' => 'pending',
            'is_read' => false,
        ]);

        Mail::to($proposal->student->email)
            ->send(new TeacherProposalRequestMail($request));

        return $request;
    });

    return response()->json([
        'message' => 'Request sent successfully.',
        'balance' => $badge->fresh()->badges,
        'request' => $request
    ]);

    }


    private function deleteExpiredProposals()
        {
            Proposal::whereNotNull('expires_at')
                ->where('expires_at', '<=', now())
                ->delete();
        }


    public function received()
        {

            $this->deleteExpiredProposals();

            $student = auth()->user();

            $requests = TeacherRequest::with([
                'teacher',
                'teacherForm.courseTitle',
                'proposal'
            ])
            ->where('student_id', $student->id)
            ->whereHas('proposal', function ($q) {
                $q->whereNull('expires_at')
                ->orWhere('expires_at', '>', now());
            })
            ->latest()
            ->get();

            $requests->each(function ($request) {

                    if ($request->teacherForm) {

                        $course = \App\Models\Coursetitle::find(
                            $request->teacherForm->coursetitle_id
                        );

                        $request->teacherForm->coursetitle_name =
                            $course?->name;

                       $request->teacherForm->specialization = 
                       is_array($request->teacherForm->specialization)
                        ? $request->teacherForm->specialization
                        : [];
                    }

                });

            return response()->json($requests);
        }


        public function removeCancelledRequest($id)
        {
            $student = auth()->user();

            $request = TeacherRequest::where('student_id', $student->id)
                ->findOrFail($id);

            $request->delete();

            return response()->json([
                'message' => 'Request removed successfully.'
            ]);
        }

public function accept($id)
{
    $student = auth()->user();

    $request = TeacherRequest::with([
        'teacher',
        'student',
        'proposal'
    ])
    ->where('student_id', $student->id)
    ->findOrFail($id);

    $request->update([
        'status' => 'accepted'
    ]);

    Mail::to($request->teacher->email)
        ->send(new TeacherRequestAccepted($request));

    return response()->json([
        'message' => 'Teacher approved successfully.',
        'status' => 'accepted'
    ]);
}


    public function decline($id)
        {
            $student = auth()->user();

            $request = TeacherRequest::with([
                'teacher',
                'student'
            ])
            ->where('student_id', $student->id)
            ->findOrFail($id);

            $request->update([
                'status' => 'declined'
            ]);

            Mail::to($request->teacher->email)
                ->send(new StudentCancelledRequestMail($request));

            return response()->json([
                'message' => 'Teacher request declined.'
            ]);
        }

public function messageRequest($requestId)
{
    $student = auth()->user();

    $request = TeacherRequest::with('teacher')
        ->findOrFail($requestId);

    if ($request->student_id != $student->id) {
        return response()->json([
            'message' => 'Unauthorized.'
        ], 403);
    }

    if ($request->status !== 'accepted') {
        return response()->json([
            'message' => 'Teacher has not been accepted.'
        ], 422);
    }

    $userOne = min($student->id, $request->teacher_id);
    $userTwo = max($student->id, $request->teacher_id);

    $chat = Chat::firstOrCreate([
        'user_one_id' => $userOne,
        'user_two_id' => $userTwo,
        'type' => 'private',
    ]);

    $messages = Message::with([
        'sender:id,first_name,last_name,image'
    ])
    ->where('chat_id', $chat->id)
    ->latest()
    ->get()
    ->reverse()
    ->values();

    $other = $request->teacher;

    return response()->json([
        'chat' => [
            ...$chat->toArray(),
            'other' => [
                'id' => $other->id,
                'first_name' => $other->first_name,
                'last_name' => $other->last_name,
                'image' => $other->image,
            ]
        ],
        'messages' => $messages,
    ]);
}

public function history()
{
    $this->deleteExpiredProposals();

    $teacher = auth()->user();

    if ($teacher->role != "admin") {
        return response()->json([
            "message" => "Only teachers."
        ], 403);
    }

    $history = TeacherRequest::with([
        'student:id,first_name,last_name,image',
        'proposal:id,title,subject,price,currency,teacher_type,preferred_location,teaching_hours,from_time,to_time,description,student_deleted,expires_at'
    ])
    ->where('teacher_id', $teacher->id)
    ->where('teacher_deleted', false)
    ->whereHas('proposal', function ($q) {
        $q->whereNull('expires_at')
          ->orWhere('expires_at', '>', now());
    })
    ->latest()
    ->get();

    return response()->json($history);
}


public function cancelRequest($id)
{
    $teacher = auth()->user();

    $request = TeacherRequest::where(
        'teacher_id',
        $teacher->id
    )
    ->findOrFail($id);

    if($request->status != 'pending'){

        return response()->json([

            'message'=>'Only pending request can be cancelled.'

        ],400);

    }

    $request->update([

        'status'=>'cancelled'

    ]);

    return response()->json([

        'message'=>'Request cancelled.'

    ]);
}

public function deleteHistory($id)
{
    $teacher = auth()->user();

    $request = TeacherRequest::where(
        'teacher_id',
        $teacher->id
    )
    ->findOrFail($id);

    if($request->status == 'pending'){

        return response()->json([

            'message'=>'Cancel the request first.'

        ],400);

    }

    $request->update([

        'teacher_deleted'=>true

    ]);

    return response()->json([

        'message'=>'Removed from history.'

    ]);
}


public function requestNotification()
{
    $student = auth()->user();

    $count = TeacherRequest::where('student_id', $student->id)
        ->where('status', 'pending')
        ->where('is_read', false)
        ->count();

    return response()->json([
        'pending_requests' => $count,
    ]);
}

public function markRequestAsRead()
{
    TeacherRequest::where('student_id', auth()->id())
        ->where('status', 'pending')
        ->where('is_read', false)
        ->update([
            'is_read' => true
        ]);

    return response()->json([
        'success' => true
    ]);
}

}