<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\StudentFriendRequest;
use Illuminate\Support\Facades\Mail;
use App\Mail\StudentFriendRequested;
use App\Mail\StudentFriendAccepted;
use App\Mail\StudentFriendDeclined;
use App\Models\Chat;
use App\Models\User;
use App\Models\HiddenUser;
use Carbon\Carbon;




class StudentFriendController extends Controller

{
public function studentsToAdd(Request $request)
{
    $user = $request->user();

    if ($user->role !== 'student') {
        return response()->json(['students' => []]);
    }

    $students = User::where('role', 'student')
        ->where('id', '!=', $user->id)

        // ❌ Exclude anyone with pending or accepted request (any direction)
        ->whereNotExists(function ($q) use ($user) {
            $q->selectRaw(1)
              ->from('student_friend_requests')
              ->whereIn('status', ['pending', 'accepted'])
              ->where(function ($x) use ($user) {
                  $x->whereColumn('student_friend_requests.user_id', $user->id)
                    ->whereColumn('student_friend_requests.student_id', 'users.id')
                  ->orWhere(function ($y) use ($user) {
                      $y->whereColumn('student_friend_requests.user_id', 'users.id')
                        ->whereColumn('student_friend_requests.student_id', $user->id);
                  });
              });
        })

        // ❌ Exclude users whose request is hidden by me and still active
        ->whereNotExists(function ($q) use ($user) {
    $q->selectRaw(1)
      ->from('student_friend_requests as sfr')
      ->join('hidden_student_friend_requests as h', 'h.student_friend_request_id', '=', 'sfr.id')
      ->where('h.user_id', $user->id)
      ->where('h.hidden_until', '>', now())
      ->where(function ($x) {
          $x->whereColumn('sfr.user_id', 'users.id')
            ->orWhereColumn('sfr.student_id', 'users.id');
      });
})


        ->get();

    return response()->json([
        'students' => $students
    ]);
}


public function sendRequest(Request $request)
{
    $user = $request->user();
    $studentId = $request->student_id;

    if ($user->id == $studentId) {
        return response()->json([
            'message' => 'You cannot add yourself'
        ], 422);
    }

    // Find any existing request between both users
    $existing = StudentFriendRequest::where(function ($q) use ($user, $studentId) {
        $q->where('user_id', $user->id)
          ->where('student_id', $studentId);
    })->orWhere(function ($q) use ($user, $studentId) {
        $q->where('user_id', $studentId)
          ->where('student_id', $user->id);
    })->first();

    /**
     * 🚫 Already pending → block
     */
    if ($existing && $existing->status === 'pending') {
        return response()->json([
            'message' => 'Request already pending'
        ], 409);
    }

    /**
     * 🔄 Previously declined
     */
    if ($existing && $existing->status === 'declined') {

        // ❗ Delete old request completely
        $existing->delete();
    }

    /**
     * ✅ Create NEW request with CORRECT direction
     */
    $requestModel = StudentFriendRequest::create([
        'user_id' => $user->id,       // sender
        'student_id' => $studentId,   // receiver
        'status' => 'pending',
        'hidden_for_requester' => false,
        'hidden_for_requested' => false,
    ]);


    // ✅ SAFE MAIL (NULL CHECK)
    if ($requestModel->student) {
        Mail::to($requestModel->student->email)
            ->send(new StudentFriendRequested($requestModel));
    }

    return response()->json([
        'message' => 'Request sent',
        'request' => $requestModel
    ]);
}



    // Get requests for the logged-in student
    public function allRequests(Request $request)
{
    $user = $request->user();

    if ($user->role !== 'student') {
        return response()->json(['requests' => []]);
    }

    $requests = StudentFriendRequest::with('requester')
        ->where('student_id', $user->id)
        ->where('status', 'pending')
        ->where('hidden_for_requested', false)
        ->latest()
        ->get();

    return response()->json(['requests' => $requests]);
}




    public function respond(Request $request, $id)
{
    $student = $request->user();

    $requestModel = StudentFriendRequest::where('id', $id)
        ->where('student_id', $student->id)
        ->firstOrFail();

    if (!in_array($request->action, ['accepted', 'declined'])) {
        return response()->json(['message' => 'Invalid action'], 422);
    }

    if ($request->action === 'accepted') {

    $requestModel->update([
        'status' => 'accepted'
    ]);

    [$one, $two] = collect([
        $requestModel->user_id,
        $requestModel->student_id
    ])->sort()->values();

    Chat::firstOrCreate([
        'user_one_id' => $one,
        'user_two_id' => $two,
        'type' => 'student_student',
    ]);
    }


    if ($request->action === 'accepted') {

        $requestModel->update([
            'status' => 'accepted'
        ]);

        if ($requestModel->requester) {
            Mail::to($requestModel->requester->email)
                ->send(new StudentFriendAccepted($requestModel));
        }
    }

    if ($request->action === 'declined') {

        $requestModel->update([
            'status' => 'declined'
        ]);

        if ($requestModel->requester) {
            Mail::to($requestModel->requester->email)
                ->send(new StudentFriendDeclined($requestModel));
        }
    }

    return response()->json([
        'message' => 'Request handled',
        'status'  => $requestModel->status
    ]);
}



    // Get requests sent by the student
    public function myRequests(Request $request)
{
    $user = $request->user();

    $requests = StudentFriendRequest::with('student')
        ->where('user_id', $user->id)
        ->where('status', 'pending')
        ->where('hidden_for_requester', false)
        ->latest()
        ->get();

    return response()->json(['requests' => $requests]);
}



public function relationshipStatus(Request $request, $studentId)
{
    $user = $request->user();

    $relation = StudentFriendRequest::where(function ($q) use ($user, $studentId) {
        $q->where('user_id', $user->id)
          ->where('student_id', $studentId);
    })->orWhere(function ($q) use ($user, $studentId) {
        $q->where('user_id', $studentId)
          ->where('student_id', $user->id);
    })->first();

    if (!$relation) {
        return response()->json([
            'status' => 'none'
        ]);
    }

    return response()->json([
        'status' => $relation->status,
        'direction' => $relation->user_id === $user->id
            ? 'sent'
            : 'received',
        'chat_id' => $relation->status === 'accepted'
            ? optional(
                Chat::whereJsonContains('participants', [$user->id, $studentId])->first()
              )->id
            : null
    ]);
}



public function hideUser($hiddenUserId)
{
    $userId = auth()->id();

    if ($userId == $hiddenUserId) {
        return response()->json([
            'message' => 'You cannot hide yourself'
        ], 400);
    }

    HiddenUser::updateOrCreate(
        [
            'user_id' => $userId,
            'hidden_user_id' => $hiddenUserId,
        ]
    );

    return response()->json([
        'message' => 'User removed from friend list'
    ]);
}




public function accept($id)
{
    $friend = StudentFriendRequest::where('id', $id)
        ->where('receiver_id', auth()->id())
        ->firstOrFail();

    $friend->update([
        'status' => 'accepted'
    ]);

    return response()->json([
        'message' => 'Friend request accepted'
    ]);
}


public function relation(Request $request, $profileId)
{
    $userId = $request->user()->id;

    $relation = StudentFriendRequest::where(function ($q) use ($userId, $profileId) {
        $q->where('sender_id', $userId)
          ->where('receiver_id', $profileId);
    })->orWhere(function ($q) use ($userId, $profileId) {
        $q->where('sender_id', $profileId)
          ->where('receiver_id', $userId);
    })->first();

    if (!$relation) {
        return response()->json([
            'status' => 'none',
            'direction' => null
        ]);
    }

    return response()->json([
        'status' => $relation->status,
        'direction' =>
            $relation->status === 'pending'
                ? ($relation->sender_id === $userId ? 'sent' : 'received')
                : null
    ]);
}



public function show($id, Request $request)
{
    $authId = $request->user()->id;

    $user = User::findOrFail($id);

    // 🔑 CHECK BOTH DIRECTIONS
    $relation = StudentFriendRequest::where(function ($q) use ($authId, $id) {
        $q->where('user_id', $authId)
          ->where('student_id', $id);
    })->orWhere(function ($q) use ($authId, $id) {
        $q->where('user_id', $id)
          ->where('student_id', $authId);
    })->first();

    return response()->json([
        'student' => $user,
        'status'  => $relation?->status ?? 'none',
    ]);
}

public function showAccepted($id, Request $request)
{
    $authId = $request->user()->id;
    $isOwner = $authId == $id;

    $acceptedRelations = StudentFriendRequest::where('status', 'accepted')
        ->where(function ($q) use ($id) {
            $q->where('user_id', $id)
              ->orWhere('student_id', $id);
        })
        ->with([
            'user:id,first_name,last_name',
            'student:id,first_name,last_name'
        ])
        ->get();

    $acceptedStudents = $acceptedRelations->map(function ($relation) use ($id, $authId, $isOwner) {

        $student = $relation->user_id == $id
            ? $relation->student
            : $relation->user;

        // ✅ OWNER ALWAYS ACCEPTED
        if ($isOwner) {
            $student->status = 'accepted';
            return $student;
        }

        // ✅ CORRECT visitor relation lookup
        $visitorRelation = StudentFriendRequest::where(function ($q) use ($authId, $id) {
        $q->where('user_id', $authId)
          ->where('student_id', $id);
        })->orWhere(function ($q) use ($authId, $id) {
            $q->where('user_id', $id)
            ->where('student_id', $authId);
        })->first();

        $student->status = $visitorRelation?->status ?? 'none';

        return $student;
    })->values();

    return response()->json([
        'acceptedStudents' => $acceptedStudents,
        'isOwner' => $isOwner,
    ]);
}


public function acceptedIndex(Request $request) 
{
    $authId = $request->user()->id;

    // Since this is dashboard-only, user is ALWAYS owner
    $isOwner = true;

    $acceptedRelations = StudentFriendRequest::where('status', 'accepted')
        ->where(function ($q) use ($authId) {
            $q->where('user_id', $authId)
              ->orWhere('student_id', $authId);
        })
        ->with([
            'user:id,first_name,last_name',
            'student:id,first_name,last_name'
        ])
        ->get();

    $acceptedStudents = $acceptedRelations
        ->map(function ($relation) use ($authId) {

            // Get the OTHER person in the relationship
            $student = $relation->user_id == $authId
                ? $relation->student
                : $relation->user;

            $student->status = 'accepted';

            return $student;
        })
        ->values()
        ->map(function ($student, $index) {
            $student->index = $index + 1; // ✅ Dashboard index
            return $student;
        });

    return response()->json([
        'acceptedStudents' => $acceptedStudents,
        'isOwner' => true,
    ]);
}

}
