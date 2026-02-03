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

    return response()->json([
        'message' => 'Report submitted successfully.'
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
