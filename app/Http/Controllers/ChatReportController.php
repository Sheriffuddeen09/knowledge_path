<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ChatReport;
use App\Models\Notification;
use App\Mail\UserReportedMail;
use App\Mail\ReporterConfirmationMail;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\DB;


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

    $authId = auth()->id();

    // ❌ prevent self-report
    if ($request->reported_user_id == $authId) {
        return response()->json([
            'message' => 'You cannot report yourself.'
        ], 422);
    }

    // 🔥 check membership (PRIVATE + GROUP safe)
    $isMember = DB::table('chat_user')
        ->where('chat_id', $request->chat_id)
        ->where('user_id', $authId)
        ->exists();

    if (!$isMember) {
        return response()->json([
            'message' => 'You are not allowed to report this chat'
        ], 403);
    }

    // 🔥 SAVE REPORT
    $report = ChatReport::updateOrCreate(
        [
            'chat_id' => $request->chat_id,
            'reporter_id' => $authId,
        ],
        [
            'reported_user_id' => $request->reported_user_id,
            'reason' => $request->reason,
            'details' => $request->details,
        ]
    );

    // =========================
    // 📧 EMAIL NOTIFICATIONS
    // =========================
    Mail::to($report->reportedUser->email)
        ->send(new UserReportedMail($report));

    Mail::to($report->reporter->email)
        ->send(new ReporterConfirmationMail($report));

    // =========================
    // 🔔 SYSTEM NOTIFICATION
    // =========================
    Notification::create([
        'user_id' => $request->reported_user_id,
        'type' => 'chat_reported',
        'data' => json_encode([
            'chat_id' => $request->chat_id,
            'reporter_id' => $authId,
            'reporter_name' => auth()->user()->first_name . ' ' . auth()->user()->last_name,
        ]),
        'redirect_url' => "/chat/report/{$request->chat_id}",
        'read' => false
    ]);

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
