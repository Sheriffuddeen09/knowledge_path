<?php

namespace App\Http\Controllers;

use App\Models\Report;
use App\Models\MessageReport;
use App\Models\User;
use App\Models\Video;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use App\Mail\ReporterMail;
use App\Mail\UserReportedMail;
use App\Mail\ReportedUserMail;
use App\Mail\ReporterUserMail;
use Illuminate\Support\Facades\Auth;



class ReportController extends Controller
{

public function store(Request $request)
{
    $request->validate([
        'video_id' => 'nullable|exists:videos,id',
        'reported_user_id' => 'required|exists:users,id',
        'category' => 'required|string|max:255',
        'description' => 'required|string',
    ]);

    $reporter = Auth::user(); // 👈 logged-in user

    if (!$reporter) {
        return response()->json(['message' => 'Unauthenticated'], 401);
    }

    $report = Report::create([
        'video_id' => $request->video_id,
        'reporter_id' => $reporter->id, // 👈 auto set
        'reported_user_id' => $request->reported_user_id,
        'category' => $request->category,
        'description' => $request->description,
    ]);

    $videoTitle = $request->video_id
        ? optional(Video::find($request->video_id))->title
        : 'No specific video';

    Mail::to($reporter->email)
        ->send(new ReporterMail($videoTitle));

    Mail::to(User::find($request->reported_user_id)->email)
        ->send(new ReportedUserMail($videoTitle));

    if ($request->reported_user_id == auth()->id()) {
    return response()->json([
        'message' => 'You cannot report yourself.'
    ], 422);
}

    return response()->json([
        'message' => 'Report submitted successfully'
    ]);
}


    public function index()
    {
        $reports = Report::with(['reporter', 'reportedUser', 'video'])
                         ->orderBy('created_at', 'desc')
                         ->get();

        return response()->json($reports);
    }



    public function storeReport(Request $request)
{
    $request->validate([
        'message_id' => 'required|exists:messages,id',
        'reported_user_id' => 'required|exists:users,id',
        'reason' => 'required|string',
        'details' => 'nullable|string',
    ]);

    $report = MessageReport::create([
        'message_id' => $request->message_id,
        'reporter_id' => auth()->id(),
        'reported_user_id' => $request->reported_user_id,
        'reason' => $request->reason,
        'details' => $request->details,
    ]);

    $reporter = auth()->user();
    $reportedUser = User::find($request->reported_user_id);

    Mail::to(auth()->user()->email)->send(new ReporterUserMail($report));

    // Send email to reported user
    $reportedUser = User::find($request->reported_user_id);
    Mail::to($reportedUser->email)->send(new UserReportedMail($report));

    if ($request->reported_user_id == auth()->id()) {
    return response()->json([
        'message' => 'You cannot report yourself.'
    ], 422);
}

    return response()->json([
        'message' => 'Report submitted successfully.'
    ]);
}


   

public function getReports()
{
    $reports = MessageReport::with(['reporter', 'reported_user', 'message'])->latest()->get();

    return response()->json($reports);
}



}
