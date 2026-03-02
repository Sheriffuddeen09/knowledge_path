<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\PostReport;
use App\Mail\PostReportedMail;
use App\Mail\PostReporterConfirmationMail;
use Illuminate\Support\Facades\Mail;


class PostReportController extends Controller
{
  

public function store(Request $request)
{
    $request->validate([
        'post_id' => 'required|exists:posts,id',
        'reported_user_id' => 'required|exists:users,id',
        'reason' => 'required|string',
        'details' => 'nullable|string',
    ]);

    if ($request->reported_user_id == auth()->id()) {
        return response()->json([
            'message' => 'You cannot report yourself.'
        ], 422);
    }

    $report = PostReport::updateOrCreate(
        [
            'post_id' => $request->post_id,
            'reporter_id' => auth()->id(),
        ],
        [
            'reported_user_id' => $request->reported_user_id,
            'reason' => $request->reason,
            'details' => $request->details,
        ]
    );

    // Send emails
    Mail::to($report->reportedUser->email)
        ->send(new PostReportedMail($report));

    Mail::to($report->reporter->email)
        ->send(new PostReporterConfirmationMail($report));

    Notification::create([
    'user_id' => $request->reported_user_id,
    'type' => 'post_reported',
    'data' => json_encode([
        'post_id' => $request->post_id,
        'reporter_id' => auth()->id(),
        'reporter_name' => auth()->user()->first_name . ' ' . auth()->user()->last_name,
    ]),
    'redirect_url' => "/post/report/{$request->post_id}",
    'read' => false
    ]);

    return response()->json([
        'message' => 'Report submitted successfully.'
    ]);
}


public function getPostReport($postId)
{
    $userId = auth()->id();

    $report = PostReport::where('post_id', $postId)
        ->where('reported_user_id', $userId) // show only to reported user
        ->with(['reporter:id,first_name,last_name,email', 'post:id,title,content'])
        ->first();

    if (!$report) {
        return response()->json([
            'message' => 'Report not found or you are not authorized to view it.'
        ], 404);
    }

    return response()->json([
        'report_id' => $report->id,
        'post_id' => $report->post_id,
        'reporter' => [
            'id' => $report->reporter->id,
            'name' => $report->reporter->first_name . ' ' . $report->reporter->last_name,
            'email' => $report->reporter->email,
        ],
        'reason' => $report->reason,
        'details' => $report->details,
        'created_at' => $report->created_at->toDateTimeString(),
        'post' => $report->post, // optional post data
    ]);
}



public function index()
{
    return PostReport::with([
        'post',
        'reporter:id,first_name,last_name,email',
        'reportedUser:id,first_name,last_name,email'
    ])
    ->latest()
    ->get();
}


}
