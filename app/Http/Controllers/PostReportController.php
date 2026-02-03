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

    return response()->json([
        'message' => 'Report submitted successfully.'
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
