<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\LiveClassRequest;
use Illuminate\Support\Facades\Mail;
use App\Mail\StudentRequestedLiveClass;
use App\Mail\LiveClassAccepted;
use App\Mail\LiveClassDeclined;
use App\Models\Chat;



class LiveClassController extends Controller
{

public function sendRequest(Request $request)
{
    $user = $request->user();
    $teacherId = $request->teacher_id;

    // Find any existing request (regardless of cleared flags)
    $existing = LiveClassRequest::where('user_id', $user->id)
        ->where('teacher_id', $teacherId)
        ->first();

    // ❌ Block if pending or accepted AND student has not cleared
    if ($existing && in_array($existing->status, ['pending', 'accepted']) && !$existing->cleared_by_student) {
        return response()->json([
            'status' => false,
            'message' => 'Request already sent'
        ], 409);
    }

    // ✅ Resend if declined OR student has cleared previous request
    if ($existing && ($existing->status === 'declined' || $existing->cleared_by_student)) {
        $existing->update([
            'status' => 'pending',
            'cleared_by_student' => false,
            'cleared_by_teacher' => false, // 🔹 Reset so teacher sees it
        ]);

        // Send email to teacher
        Mail::to($existing->teacher->email)
            ->send(new StudentRequestedLiveClass($existing));

        return response()->json([
            'status' => true,
            'message' => 'Request sent successfully',
            'request' => $existing,
        ]);
    }

    // ✅ New request (no existing request)
    $requestModel = LiveClassRequest::create([
        'user_id' => $user->id,
        'teacher_id' => $teacherId,
    ]);

    // Send email to teacher
    Mail::to($requestModel->teacher->email)
        ->send(new StudentRequestedLiveClass($requestModel));

    return response()->json([
        'status' => true,
        'message' => 'Request sent successfully',
        'request' => $requestModel,
    ]);
}


    // Get requests for the logged-in teacher
    public function allRequests(Request $request)
{
    $teacher = $request->user();

    $requests = $teacher->liveRequestsReceived()
        ->with('student')
        ->where(function($q) {
            $q->where('status', 'pending') // Always show pending requests
              ->orWhere(function($q2) {
                  $q2->whereIn('status', ['accepted','declined'])
                     ->where('cleared_by_teacher', false); // Show accepted/declined not cleared
              });
        })
        ->get();

    return response()->json([
        'status' => true,
        'requests' => $requests,
        'pending_requests' => $requests->where('status', 'pending')->count(),
    ]);
}





    public function respond(Request $request, $id)
    {
    
        $teacher = $request->user();

    $requestModel = LiveClassRequest::with(['student', 'teacher'])
        ->where('id', $id)
        ->where('teacher_id', $teacher->id)
        ->firstOrFail();

    $status = $request->action; // accepted | declined

    if (!in_array($status, ['accepted', 'declined'])) {
        return response()->json([
            'status' => false,
            'message' => 'Invalid action'
        ], 422);
    }

    $requestModel->status = $status;
    $requestModel->save();

    if ($status === 'accepted') {

        Chat::firstOrCreate([
            'teacher_id' => $requestModel->teacher_id,
            'student_id' => $requestModel->user_id,
        ]);

        Mail::to($requestModel->student->email)
            ->send(new LiveClassAccepted($requestModel));
    }

    if ($status === 'declined') {
        Mail::to($requestModel->student->email)
            ->send(new LiveClassDeclined($requestModel));
    }

    return response()->json([
        'status' => true,
        'message' => "Request {$status} successfully",
        'request' => $requestModel,
    ]);
}

    // Get requests sent by the student
    public function myRequests(Request $request)
{
    $user = $request->user();

    $requests = $user->liveRequestsSent()
        ->with('teacher')
        ->where('cleared_by_student', false)
        ->get();

    return response()->json([
        'status' => true,
        'requests' => $requests,
    ]);
}


public function clearRequestByStudent($id, Request $request)
{
    $user = $request->user();

    $requestModel = LiveClassRequest::where('id', $id)
        ->where('user_id', $user->id)
        ->whereIn('status', ['accepted', 'declined'])
        ->firstOrFail();

    $requestModel->update([
        'cleared_by_student' => true,
    ]);

    return response()->json([
        'status' => true,
        'message' => 'Request history removed',
    ]);
}




public function clearByTeacher($id, Request $request)
{
    $teacher = $request->user();

    $requestModel = LiveClassRequest::where('id', $id)
        ->where('teacher_id', $teacher->id)
        ->whereIn('status', ['accepted', 'declined'])
        ->firstOrFail();

    $requestModel->update([
        'cleared_by_teacher' => true,
    ]);

    return response()->json([
        'status' => true,
        'message' => 'Request history removed',
    ]);
}

public function requestsSummary(Request $request)
{
    $teacher = $request->user();

    // Fetch all requests with student info
    $requests = LiveClassRequest::with('student')
        ->where('teacher_id', $teacher->id)
        ->get();

    // Optional: categorize by status
    $data = [
        'all' => $requests,
        'accepted' => $requests->where('status', 'accepted')->values(),
        'pending' => $requests->where('status', 'pending')->values(),
        'declined' => $requests->where('status', 'declined')->values(),
    ];

    return response()->json([
        'status' => true,
        'data' => $data,
    ]);
}



}
