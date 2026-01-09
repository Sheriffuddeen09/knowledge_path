<?php

namespace App\Http\Controllers;

use App\Models\Assignment;
use App\Models\User;
use App\Models\AssignmentQuestion;
use App\Models\AssignmentResult;
use App\Models\AssignmentAnswer;
use App\Models\AssignmentSubmission;
use App\Models\AssignmentAttempt;
use Illuminate\Http\Request;
use App\Notifications\AssignmentRescheduled;
use App\Models\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;




class AssignmentController extends Controller
{
    // GET /api/assignments
    public function index()
{
    return Assignment::withCount('submissions')
        ->where('teacher_id', auth()->id())
        ->where('due_at', '>', now()) 
        ->latest()
        ->get();
}


       
    public function preview($id)
    {
        $assignment = Assignment::with([
            'questions',
            'submissions' => function ($q) {
                $q->select('id', 'assignment_id', 'student_id');
            }
        ])
        ->where('teacher_id', auth()->id())
        ->findOrFail($id);

        return response()->json($assignment);
    }


 public function reset(Assignment $assignment)
{
    // Ownership check
    if ($assignment->teacher_id !== auth()->id()) {
        abort(403);
    }

    // ❌ If any student has attempted → block reset
    $attempted = AssignmentSubmission::where('assignment_id', $assignment->id)
        ->exists();

    if ($attempted) {
        return response()->json([
            'message' => 'Cannot reset. Assignment has been attempted.'
        ], 409);
    }

    // ✅ Reset = generate new token + extend due date
    $assignment->update([
        'access_token' => Str::uuid(),
        'due_at' => now()->addDays(1),
        'is_rescheduled' => false,
    ]);

    return response()->json([
        'message' => 'Assignment reset successfully',
        'assignment' => $assignment
    ]);
}




    // GET /api/assignments/{id}
    public function show($id)
    {
        return Assignment::with('submissions.student')->findOrFail($id);
    }


    // POST /api/assignments


public function store(Request $request)
{
    $request->validate([
        'title' => 'required|string',
        'due_at' => 'required|date',
        'duration_minutes' => 'required|integer|min:1',
        'questions' => 'required|array|min:1|max:20',

        'questions.*.question' => 'required|string',
        'questions.*.A' => 'required|string',
        'questions.*.B' => 'required|string',
        'questions.*.C' => 'required|string',
        'questions.*.D' => 'required|string',
        'questions.*.answer' => 'required|in:A,B,C,D',
    ]);

    DB::beginTransaction();

    try {
        $assignment = Assignment::create([
            'teacher_id' => auth()->id(),
            'title' => $request->title,
            'duration_minutes' => $request->duration_minutes,
            'due_at' => $request->due_at,
            'access_token' => Str::uuid(),
        ]);

        foreach ($request->questions as $q) {
            AssignmentQuestion::create([
                'assignment_id' => $assignment->id,
                'question' => $q['question'],
                'option_a' => $q['A'],
                'option_b' => $q['B'],
                'option_c' => $q['C'],
                'option_d' => $q['D'],
                'correct_answer' => $q['answer'],
            ]);
        }

        User::where('role', 'student')->each(function ($student) use ($assignment) {
            Notification::create([
                'user_id' => $student->id,
                'type' => 'assignment_created',
                'data' => [
                    'assignment_id' => $assignment->id,
                    'access_token' => $assignment->access_token,
                ],
            ]);
        });

        DB::commit();

        return response()->json([
            'message' => 'Assignment created successfully',
            'assignment_id' => $assignment->id,
            'access_token' => $assignment->access_token,
        ], 201);

    } catch (\Throwable $e) {
        DB::rollBack();

        return response()->json([
            'error' => $e->getMessage(), // 🔥 TEMP DEBUG
        ], 500);
    }
}


public function submit(Request $request, Assignment $assignment)
{
    $studentId = auth()->id();

    if (!$request->filled('answers') || count($request->answers) === 0) {
        return response()->json([
            'message' => 'Cannot submit empty assignment'
        ], 422);
    }

    // Prevent resubmission
    if (
        AssignmentResult::where('assignment_id', $assignment->id)
            ->where('student_id', $studentId)
            ->whereNotNull('submitted_at')
            ->exists()
    ) {
        return response()->json(['message' => 'Already submitted'], 409);
    }

    $submission = AssignmentSubmission::where('assignment_id', $assignment->id)
        ->where('student_id', $studentId)
        ->firstOrFail();

    $score = 0;

    foreach ($request->answers as $questionId => $answer) {
        $question = AssignmentQuestion::findOrFail($questionId);

        AssignmentAnswer::create([
            'assignment_id' => $assignment->id,
            'question_id' => $questionId,
            'student_id' => $studentId,
            'selected_answer' => $answer,
        ]);

        if ($question->correct_answer === $answer) {
            $score++;
        }
    }

    $isLate = now()->greaterThan($assignment->due_at);

    // ✅ CREATE RESULT
    AssignmentResult::create([
        'assignment_id' => $assignment->id,
        'student_id' => $studentId,
        'score' => $score,
        'total_questions' => $assignment->questions()->count(),
        'is_late' => $isLate,
        'submitted_at' => now(),
    ]);

    // ✅ MARK SUBMISSION AS SUBMITTED (THIS FIXES LIBRARY)
    $submission->update([
        'submitted_at' => now(),
        'locked' => 1,
    ]);

    // Notify teacher
    Notification::create([
        'user_id' => $assignment->teacher_id,
        'type' => 'assignment_submitted',
        'data' => [
            'student_id' => $studentId,
            'assignment_id' => $assignment->id,
            'score' => $score,
        ],
    ]);

    return response()->json([
        'score' => $score,
        'is_late' => $isLate,
    ]);
}


public function block($id)
{
    $assignment = Assignment::where('id', $id)
        ->where('teacher_id', auth()->id())
        ->firstOrFail();

    $assignment->update(['is_blocked' => true]);

    return response()->json([
        'message' => 'Assignment blocked'
    ]);
}

public function unblock($id)
{
    $assignment = Assignment::where('id', $id)
        ->where('teacher_id', auth()->id())
        ->firstOrFail();

    $assignment->update(['is_blocked' => false]);

    return response()->json([
        'message' => 'Assignment unblocked'
    ]);
}






public function unreadCount()
{
    return Notification::where('user_id', auth()->id())
        ->whereNull('read_at')
        ->count();
}


public function submitByToken(Request $request, string $token)
{
    $assignment = Assignment::where('access_token', $token)
        ->with('questions')
        ->firstOrFail();

    return $this->submit($request, $assignment);
}


public function start($token)
{
    $assignment = Assignment::where('access_token', $token)
        ->with('questions')
        ->firstOrFail();

     if ($assignment->is_blocked) {
        return response()->json([
            'status' => 'blocked',
            'message' => 'Sorry, this assignment link has been blocked. Please contact your teacher to unblock it or reshare another link.'
        ], 403);
    }


    $studentId = auth()->id();

    // 🔴 FINAL SUBMISSION CHECK (ONLY FINALIZED SUBMISSIONS)
    $submitted = AssignmentResult::where([
        'assignment_id' => $assignment->id,
        'student_id' => $studentId,
    ])->whereNotNull('submitted_at')->exists();

    if ($submitted) {
        return response()->json(['status' => 'submitted']);
    }

    // 🔵 EXISTING ATTEMPT
    $submission = AssignmentSubmission::where([
        'assignment_id' => $assignment->id,
        'student_id' => $studentId,
    ])->first();

    // 🔒 Expired but never started
    

    if (!$submission && now()->greaterThan($assignment->due_at->endOfDay())) {
    return response()->json([
        'status' => 'expired_not_started',
        'assignment_id' => $assignment->id,
        'due_at' => $assignment->due_at,
    ]);
}


    // ▶ Resume
    if ($submission) {
        return response()->json([
            'status' => 'in_progress',
            'assignment' => $assignment,
            'answers' => $submission->answers,
            'current_index' => $submission->current_index,
            'remaining_seconds' => $submission->remaining_seconds,
            'started_at' => $submission->started_at,
        ]);
    }

    \Log::info('Submission check', [
    'exists' => AssignmentResult::where([
        'assignment_id' => $assignment->id,
        'student_id' => auth()->id(),
    ])->exists()
]);


    // 🆕 Fresh access
    return response()->json([
        'status' => 'new',
        'assignment' => $assignment,
    ]);
}


public function begin($token)
{
    $assignment = Assignment::where('access_token', $token)->firstOrFail();
    $studentId = auth()->id();

    $submission = AssignmentSubmission::firstOrCreate(
        [
            'assignment_id' => $assignment->id,
            'student_id' => $studentId,
        ],
        [
            'remaining_seconds' => $assignment->duration_minutes * 60,
        ]
    );

    // ❌ Already submitted → block
    if ($submission->submitted_at) {
        return response()->json([
            'status' => 'already_submitted',
        ], 409);
    }

    // ✅ If not started yet → start now
    if (!$submission->started_at) {
        $submission->update([
            'started_at' => now(),
            'remaining_seconds' => $submission->remaining_seconds
                ?? $assignment->duration_minutes * 60,
        ]);

        return response()->json([
            'status' => 'started',
        ]);
    }

    // 🔁 Already started → resume
    return response()->json([
        'status' => 'resume',
        'remaining_seconds' => $submission->remaining_seconds,
    ]);
}


public function library()
{
    return AssignmentSubmission::with('assignment')
        ->where('student_id', auth()->id())
        ->whereNull('submitted_at') // ❌ hide submitted
        ->whereHas('assignment', function ($q) {
            $q->where('due_at', '>', now()); // ❌ hide expired
        })
        ->latest()
        ->get();
}


public function saveProgress(Request $request)
{
    $request->validate([
        'assignment_id' => 'required|exists:assignments,id',
        'answers' => 'nullable|array',
        'current_index' => 'required|integer|min:0',
        'remaining_seconds' => 'required|integer|min:0',
    ]);

    $studentId = auth()->id();

    $submission = AssignmentSubmission::where([
        'assignment_id' => $request->assignment_id,
        'student_id' => $studentId,
    ])->first();

    // ❌ No submission → cannot save
    if (!$submission) {
        return response()->json([
            'error' => 'Assignment not started',
        ], 403);
    }

    // ❌ Already submitted → lock
    if ($submission->submitted_at) {
        return response()->json([
            'error' => 'Assignment already submitted',
        ], 403);
    }

    // ✅ Save progress
    $submission->update([
        'answers' => $request->answers,
        'current_index' => $request->current_index,
        'remaining_seconds' => $request->remaining_seconds,
        'last_saved_at' => now(),
    ]);

    return response()->json([
        'status' => 'saved',
    ]);
}

public function resume($token)
{
    $assignment = Assignment::where('access_token', $token)->firstOrFail();

    $attempt = AssignmentSubmission::where([
        'assignment_id' => $assignment->id,
        'student_id' => auth()->id(),
    ])->first();

    if (!$attempt) {
        return response()->json(['status' => 'new']);
    }

    return response()->json([
        'status' => 'in_progress',
        'answers' => $attempt->answers ?? [],
        'current_index' => $attempt->current_index ?? 0,
        'remaining_seconds' => $attempt->remaining_seconds,
        'started_at' => $attempt->started_at,
    ]);
}


public function restart(Request $request, string $token)
{
    $studentId = auth()->id();

    $assignment = Assignment::where('access_token', $token)->firstOrFail();

    // 🔴 Block if already finally submitted
    $submitted = AssignmentResult::where([
        'assignment_id' => $assignment->id,
        'student_id' => $studentId,
    ])->exists();

    if ($submitted) {
        return response()->json([
            'message' => 'Assignment already submitted'
        ], 409);
    }

    $submission = AssignmentSubmission::where([
        'assignment_id' => $assignment->id,
        'student_id' => $studentId,
    ])->first();

    if (!$submission) {
        return response()->json([
            'message' => 'No attempt found'
        ], 404);
    }


    // 🔄 RESET
    $submission->update([
        'answers' => [],
        'current_index' => 0,
        'remaining_seconds' => $assignment->duration_minutes * 60,
        'started_at' => null,
    ]);

    return response()->json([
        'message' => 'Assignment restarted successfully'
    ]);
}



public function reschedule(Request $request)
{
    $request->validate([
        'assignment_id' => 'required|exists:assignments,id',
        'new_due_at' => 'required|date|after:now',
    ]);

    $studentId = auth()->id();
    $assignment = Assignment::findOrFail($request->assignment_id);

    // ❌ Cannot reschedule after starting
    $started = AssignmentSubmission::where([
        'assignment_id' => $assignment->id,
        'student_id' => $studentId,
    ])->whereNotNull('started_at')->exists();

    if ($started) {
        return response()->json([
            'message' => 'Cannot reschedule after starting',
        ], 403);
    }

    $submission = AssignmentSubmission::firstOrCreate(
        [
            'assignment_id' => $assignment->id,
            'student_id' => $studentId,
        ],
        [
            'reschedule_count' => 0,
        ]
    );

    // 🔒 Limit reschedules
    $MAX_EXTENSIONS = 1;

    if ($submission->reschedule_count >= $MAX_EXTENSIONS) {
        return response()->json([
            'message' => 'Reschedule limit reached',
            'locked' => true,
        ], 403);
    }

    $originalDue = Carbon::parse($assignment->due_at);
    $requestedDate = Carbon::parse($request->new_due_at);

    // ❌ Prevent extreme extensions (max 7 days)
    if ($requestedDate->gt($originalDue->copy()->addDays(7))) {
        return response()->json([
            'message' => 'You can only reschedule up to 7 days',
        ], 422);
    }

    // ✅ Save student-specific due date
    $submission->update([
        'reschedule_due_at' => $requestedDate,
        'reschedule_count' => $submission->reschedule_count + 1,
    ]);

    // 🔔 Notify teacher
    Notification::create([
        'user_id' => $assignment->teacher_id,
        'type' => 'assignment_rescheduled',
        'data' => [
            'assignment_id' => $assignment->id,
            'student_id' => $studentId,
            'old_due_at' => $originalDue,
            'new_due_at' => $requestedDate,
        ],
    ]);

    return response()->json([
        'message' => 'Assignment rescheduled',
        'new_due_at' => $requestedDate,
        'locked' => true,
    ]);
}



public function analytics($id)
{
    $assignment = Assignment::with('submissions')
        ->where('teacher_id', auth()->id())
        ->findOrFail($id);

    return response()->json([
        'total_students' => $assignment->submissions->count(),
        'average_score' => round($assignment->submissions->avg('score'), 1),
        'highest_score' => $assignment->submissions->max('score'),
        'lowest_score' => $assignment->submissions->min('score'),
        'scores' => $assignment->submissions->pluck('score')
    ]);
}



// composer require barryvdh/laravel-dompdf composer require maatwebsite/excel

public function exportPdf($assignmentId)
{
    $results = AssignmentResult::with('student')
        ->where('assignment_id', $assignmentId)
        ->get();

    $pdf = PDF::loadView('exports.assignment_pdf', compact('results'));

    return $pdf->download('assignment-results.pdf');
}


public function exportExcel($assignmentId)
{
    return Excel::download(
        new AssignmentResultsExport($assignmentId),
        'assignment-results.xlsx'
    );
}

}
