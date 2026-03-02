<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\CommentReport;
use App\Mail\CommentReportedMail;
use App\Mail\CommentReporterConfirmationMail;
use Illuminate\Support\Facades\Mail;


class CommentReportController extends Controller
{
  

public function store(Request $request)
{
    $request->validate([
        'comment_id' => 'required|exists:post_comments,id',
        'reported_user_id' => 'required|exists:users,id',
        'reason' => 'required|string',
        'details' => 'nullable|string',
    ]);

    if ($request->reported_user_id == auth()->id()) {
        return response()->json([
            'message' => 'You cannot report yourself.'
        ], 422);
    }

    $report = CommentReport::updateOrCreate(
        [
            'comment_id' => $request->comment_id,
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
        ->send(new CommentReportedMail($report));

    Mail::to($report->reporter->email)
        ->send(new CommentReporterConfirmationMail($report));

    Notification::create([
    'user_id' => $request->reported_user_id,
    'type' => 'comment_reported',
    'data' => json_encode([
        'comment_id' => $request->comment_id,
        'reporter_id' => auth()->id(),
        'reporter_name' => auth()->user()->first_name . ' ' . auth()->user()->last_name,
    ]),
    'redirect_url' => "/comment/report/{$request->comment_id}",
    'read' => false
    ]);

    return response()->json([
        'message' => 'Report submitted successfully.'
    ]);
}



public function getCommentReport($commentId)
{
    $userId = auth()->id();

    $report = CommentReport::where('comment_id', $commentId)
        ->where('reported_user_id', $userId) // show only to reported user
        ->with([
            'reporter:id,first_name,last_name,email',
            'comment:id,post_id,content' // include comment info
        ])
        ->first();

    if (!$report) {
        return response()->json([
            'message' => 'Report not found or you are not authorized to view it.'
        ], 404);
    }

    return response()->json([
        'report_id' => $report->id,
        'comment_id' => $report->comment_id,
        'reporter' => [
            'id' => $report->reporter->id,
            'name' => $report->reporter->first_name . ' ' . $report->reporter->last_name,
            'email' => $report->reporter->email,
        ],
        'reason' => $report->reason,
        'details' => $report->details,
        'created_at' => $report->created_at->toDateTimeString(),
        'comment' => $report->comment, // optional comment data
    ]);
}


public function index()
{
    return CommentReport::with([
        'comment',
        'reporter:id,first_name,last_name,email',
        'reportedUser:id,first_name,last_name,email'
    ])
    ->latest()
    ->get();
}


}
