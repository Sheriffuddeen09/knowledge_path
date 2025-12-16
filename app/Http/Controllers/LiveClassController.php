<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\LiveClassRequest;
use Illuminate\Support\Facades\Mail;
use App\Mail\StudentRequestedLiveClass;
use App\Mail\LiveClassAccepted;
use App\Mail\LiveClassDeclined;

class LiveClassController extends Controller
{

public function sendRequest(Request $request)
{
    $user = $request->user();
    $teacherId = $request->teacher_id;

    $existing = LiveClassRequest::where('user_id', $user->id)
        ->where('teacher_id', $teacherId)
        ->first();

    if ($existing) {
        return response()->json(['status'=>false,'message'=>'Request already sent'], 409);
    }

    $requestModel = LiveClassRequest::create([
        'user_id' => $user->id,
        'teacher_id' => $teacherId,
    ]);

    // Decode teacher_info before sending email
    $teacherInfo = json_decode($requestModel->teacher->teacher_info, true);
    $requestModel->teacher->course_title = $teacherInfo['course_title'] ?? 'N/A';

    // Send email
    Mail::to($requestModel->teacher->email)->send(new StudentRequestedLiveClass($requestModel));

    return response()->json(['status'=>true,'message'=>'Request sent','request'=>$requestModel]);
}

    // Get requests for the logged-in teacher
    public function pendingRequests(Request $request)
    {
        $teacher = $request->user();

        $requests = $teacher->liveRequestsReceived()
            ->with('student')
            ->where('status', 'pending')
            ->get();

        return response()->json([
            'status' => true,
            'requests' => $requests,
        ]);
    }

    // Respond to a request
    public function respond(Request $request, $id)
    {
        $teacher = $request->user();
        $requestModel = LiveClassRequest::where('id', $id)
            ->where('teacher_id', $teacher->id)
            ->firstOrFail();

        $status = $request->action; // accept / decline
        if (!in_array($status, ['accepted', 'declined'])) {
            return response()->json(['status' => false, 'message' => 'Invalid action'], 422);
        }

        $requestModel->status = $status;
        $requestModel->save();

        // Send email to student
        if ($status === 'accepted') {
            Mail::to($requestModel->student->email)
                ->send(new LiveClassAccepted($requestModel));
        } elseif ($status === 'declined') {
            Mail::to($requestModel->student->email)
                ->send(new LiveClassDeclined($requestModel));
        }

        return response()->json([
            'status' => true,
            'message' => "Request {$status} and email notification sent to student",
            'request' => $requestModel,
        ]);
    }

    // Get requests sent by the student
    public function myRequests(Request $request)
    {
        $user = $request->user();

        $requests = $user->liveRequestsSent()->with('teacher')->get();

        return response()->json([
            'status' => true,
            'requests' => $requests,
        ]);
    }
}
