<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\CommentReport;
use App\Models\PostComment;
use App\Mail\CommentReportedMail;
use App\Mail\CommentReporterConfirmationMail;
use Illuminate\Support\Facades\Mail;
use App\Models\Notification;


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

    // Get the comment
    $comment = PostComment::with('post')->findOrFail($request->comment_id);

    // Save report
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

    // ✅ Get recipient (owner of comment)
    $recipientId = $comment->user_id;

    // ✅ Get reporter name safely
    $reporterName = auth()->user()->first_name . ' ' . auth()->user()->last_name;

    // Create notification
    Notification::updateOrCreate(
    [
        'user_id' => $recipientId,
        'type' => 'comment_reported',
        'parent_id' => $comment->parent_id,
    ],
    [
        'data' => json_encode([
            'comment_id' => $request->comment_id,
            'reporter_id' => auth()->id(),
            'parent_id' => $comment->parent_id,
        ]),
        'redirect_url' => "/post/{$comment->post_id}#comments",
        'read' => false,
    ]
);

    return response()->json([
        'message' => 'Report submitted successfully.'
    ]);
}



public function getCommentReport($commentId)
{
    $report = CommentReport::where('comment_id', $commentId)
        ->with([
                    'reporter:id,first_name,last_name,email',
                    'comment:id,post_id,body,image,parent_id'
                ])
        ->first();

    if (!$report) {
        return response()->json([
            'message' => 'Report not found.'
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
    'comment' => [
        'id' => $report->comment->id,
        'post_id' => $report->comment->post_id,
        'parent_id' => $report->comment->parent_id,
        'body' => $report->comment->body,   // ✅ FIXED
        'image' => $report->comment->image, // ✅ FIXED
    ]
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
