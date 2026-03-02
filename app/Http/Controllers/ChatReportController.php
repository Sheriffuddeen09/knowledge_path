<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ChatReport;
use App\Models\Notification;
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

    Notification::create([
        'user_id' => $request->reported_user_id, // person being reported
        'type' => 'chat_reported',
        'data' => json_encode([
            'chat_id' => $request->chat_id,
            'reporter_id' => auth()->id(),
            'reporter_name' => auth()->user()->first_name . ' ' . auth()->user()->last_name,
        ]),
        'redirect_url' => "/chat/report/{$request->chat_id}",
        'read' => false
    ]);

    return response()->json([
        'message' => 'Report submitted successfully.'
    ]);
}


public function getChatReport($chatId)
{
    $userId = auth()->id();

    $report = ChatReport::where('chat_id', $chatId)
        ->where('reported_user_id', $userId) // show only to reported user
        ->with(['reporter:id,first_name,last_name,email', 'chat'])
        ->first();

    if (!$report) {
        return response()->json([
            'message' => 'Report not found or you are not authorized to view it.'
        ], 404);
    }

    return response()->json([
        'report_id' => $report->id,
        'chat_id' => $report->chat_id,
        'reporter' => [
            'id' => $report->reporter->id,
            'name' => $report->reporter->first_name . ' ' . $report->reporter->last_name,
            'email' => $report->reporter->email,
        ],
        'reason' => $report->reason,
        'details' => $report->details,
        'created_at' => $report->created_at->toDateTimeString(),
        'chat' => $report->chat, // optional chat data
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
