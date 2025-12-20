<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ChatReport;
use App\Mail\UserReportedMail;
use App\Mail\ReporterConfirmationMail;
use Illuminate\Support\Facades\Mail;

class ChatReportController extends Controller
{
  

public function store(Request $request)
{
    $request->validate([
        'chat_id' => 'required|exists:chats,id',
        'reported_user_id' => 'required|exists:users,id',
        'reason' => 'required|string',
        'details' => 'nullable|string',
    ]);

    if ($request->reported_user_id == auth()->id()) {
        return response()->json([
            'message' => 'You cannot report yourself.'
        ], 422);
    }

    $report = ChatReport::updateOrCreate(
        [
            'chat_id' => $request->chat_id,
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
        ->send(new UserReportedMail($report));

    Mail::to($report->reporter->email)
        ->send(new ReporterConfirmationMail($report));

    return response()->json([
        'message' => 'Report submitted successfully.'
    ]);
}


public function index()
{
    return ChatReport::with([
        'chat',
        'reporter:id,first_name,last_name,email',
        'reportedUser:id,first_name,last_name,email'
    ])
    ->latest()
    ->get();
}


}
