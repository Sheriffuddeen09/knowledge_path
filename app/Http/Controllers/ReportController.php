<?php

namespace App\Http\Controllers;

use App\Models\Report;
use App\Models\User;
use App\Models\Video;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use App\Mail\ReporterMail;
use App\Mail\ReportedUserMail;
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
}
