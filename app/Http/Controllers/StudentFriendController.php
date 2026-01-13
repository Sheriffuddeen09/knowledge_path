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

        // ❌ EXCLUDE anyone with pending or accepted request (any direction)
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
        return response()->json(['message' => 'You cannot add yourself'], 422);
    }

    $requestModel = StudentFriendRequest::where(function ($q) use ($user, $studentId) {
        $q->where('user_id', $user->id)
          ->where('student_id', $studentId);
    })->orWhere(function ($q) use ($user, $studentId) {
        $q->where('user_id', $studentId)
          ->where('student_id', $user->id);
    })->first();

    if ($requestModel && $requestModel->status === 'pending') {
        return response()->json(['message' => 'Request already pending'], 409);
    }

    if ($requestModel && $requestModel->status === 'declined') {
        $requestModel->update([
            'status' => 'pending',
            'hidden_for_requester' => false,
            'hidden_for_requested' => false,
        ]);

        return response()->json([
            'message' => 'Friend request sent again',
            'request' => $requestModel
        ]);
    }

    $requestModel = StudentFriendRequest::create([
        'user_id' => $user->id,
        'student_id' => $studentId,
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

    $requestModel = StudentFriendRequest::with('requester')
        ->where('id', $id)
        ->where('student_id', $student->id)
        ->firstOrFail();

    if (!in_array($request->action, ['accepted', 'declined'])) {
        return response()->json(['message' => 'Invalid action'], 422);
    }

    $requestModel->update([
        'status' => $request->action,
        'hidden_for_requester' => true,
        'hidden_for_requested' => true,
    ]);

    if ($request->action === 'accepted') {

            Chat::firstOrCreate([
                'participants' => json_encode([
                    $requestModel->user_id,
                    $requestModel->student_id
                ])
            ]);

            $requestModel->update([
                'status' => 'accepted',
                'hidden_for_requester' => true,
                'hidden_for_requested' => true,
            ]);
        }


    if ($request->action === 'declined') {
        Mail::to($requestModel->requester->email)
            ->send(new StudentFriendDeclined($requestModel));
    }

    return response()->json([
        'message' => 'Request handled'
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


public function removeTemporarily(Request $request, $studentId)
{
    $user = $request->user();

    $requestModel = StudentFriendRequest::where('user_id', $user->id)
        ->where('student_id', $studentId)
        ->where('status', 'pending')
        ->firstOrFail();

    $requestModel->update([
        'removed_until' => now()->addDays(30)
    ]);

    return response()->json([
        'message' => 'Request hidden for 30 days'
    ]);
}


public function showStudentProfile(Request $request, $studentId)
{
    $viewer = $request->user();

    $student = User::where('role', 'student')
        ->where('id', $studentId)
        ->firstOrFail();

    // Admin / teacher → no friend logic
    if ($viewer->role !== 'student') {
        return response()->json([
            'student' => $student,
            'friend_status' => null
        ]);
    }

    $requestModel = StudentFriendRequest::where(function ($q) use ($viewer, $studentId) {
        $q->where('user_id', $viewer->id)
          ->where('student_id', $studentId);
    })->orWhere(function ($q) use ($viewer, $studentId) {
        $q->where('user_id', $studentId)
          ->where('student_id', $viewer->id);
    })->first();

    return response()->json([
        'student' => $student,
        'friend_status' => $requestModel?->status ?? 'none',
        'request_direction' => $requestModel
            ? ($requestModel->user_id === $viewer->id ? 'sent' : 'received')
            : null,
    ]);
}


public function myFriends(Request $request)
{
    $user = $request->user();

    $friends = StudentFriendRequest::where('status', 'accepted')
        ->where(function ($q) use ($user) {
            $q->where('user_id', $user->id)
              ->orWhere('student_id', $user->id);
        })
        ->with(['user', 'student'])
        ->get()
        ->map(function ($r) use ($user) {
            return $r->user_id === $user->id
                ? $r->student
                : $r->user;
        });

    return response()->json([
        'friends' => $friends
    ]);
}

}
