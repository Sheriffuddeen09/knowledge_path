<?php

namespace App\Http\Controllers;

use App\Models\Exam;
use App\Models\User;
use App\Models\ExamQuestion;
use App\Models\ExamResult;
use App\Models\ExamAnswer;
use App\Models\ExamSubmission;
use App\Models\ExamAttempt;
use Illuminate\Http\Request;
use App\Notifications\ExamRescheduled;
use App\Models\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;
use App\Models\StudentBadge;



class ExamController extends Controller
{
    // GET /api/exams
    public function index()
{
    return Exam::withCount('submissions')
        ->where('teacher_id', auth()->id())
        ->where('due_at', '>', now()) 
        ->latest()
        ->get();
}

       
    public function preview($id)
    {
        $exam = Exam::with([
            'questions',
            'submissions' => function ($q) {
                $q->select('id', 'exam_id', 'student_id');
            }
        ])
        ->where('teacher_id', auth()->id())
        ->findOrFail($id);

        return response()->json($exam);
    

    }

    // GET /api/exams/{id}
    public function show($id)
    {
        return Exam::with('submissions.student')->findOrFail($id);
    }


    // POST /api/exams


public function store(Request $request)
{
    $request->validate([
        'title' => 'required|string',
        'due_at' => 'required|date',
        'duration_minutes' => 'required|integer|min:1',
        'questions' => 'required|array|min:1|max:50',

        'questions.*.question' => 'required|string',
        'questions.*.A' => 'required|string',
        'questions.*.B' => 'required|string',
        'questions.*.C' => 'required|string',
        'questions.*.D' => 'required|string',
        'questions.*.answer' => 'required|in:A,B,C,D',
    ]);

    DB::beginTransaction();

    try {
        $exam = Exam::create([
            'teacher_id' => auth()->id(),
            'title' => $request->title,
            'duration_minutes' => $request->duration_minutes,
            'due_at' => $request->due_at,
            'access_token' => Str::uuid(),
        ]);

        foreach ($request->questions as $q) {
            ExamQuestion::create([
                'exam_id' => $exam->id,
                'question' => $q['question'],
                'option_a' => $q['A'],
                'option_b' => $q['B'],
                'option_c' => $q['C'],
                'option_d' => $q['D'],
                'correct_answer' => $q['answer'],
            ]);
        }

        User::where('role', 'student')->each(function ($student) use ($exam) {
            Notification::create([
                'user_id' => $student->id,
                'type' => 'exam_created',
                'data' => [
                    'exam_id' => $exam->id,
                    'access_token' => $exam->access_token,
                ],
            ]);
        });

        DB::commit();

        return response()->json([
            'message' => 'Exam created successfully',
            'exam_id' => $exam->id,
            'access_token' => $exam->access_token,
        ], 201);

    } catch (\Throwable $e) {
        DB::rollBack();

        return response()->json([
            'error' => $e->getMessage(), // 🔥 TEMP DEBUG submit
        ], 500);
    }
}


public function submit(Request $request, Exam $exam)
{
    $studentId = auth()->id();

    if (!$request->filled('answers') || count($request->answers) === 0) {
        return response()->json(['message' => 'Cannot submit empty exam'], 422);
    }

    // Prevent resubmission
    if (
        ExamResult::where('exam_id', $exam->id)
            ->where('student_id', $studentId)
            ->exists()
    ) {
        return response()->json(['message' => 'Already submitted'], 409);
    }

    $totalQuestions = $exam->questions()->count();
    $isLate = now()->greaterThan($exam->due_at);

    if (count($request->answers) !== $totalQuestions) {
        return response()->json(['message' => 'Please answer all questions'], 422);
    }

    // ✅ CREATE RESULT FIRST
    $result = ExamResult::create([
        'exam_id' => $exam->id,
        'student_id' => $studentId,
        'score' => 0, // temp
        'total_questions' => $totalQuestions,
        'is_late' => $isLate,
        'submitted_at' => now(),
    ]);

    $score = 0;

    foreach ($request->answers as $questionId => $answer) {
        $question = ExamQuestion::findOrFail($questionId);

        ExamAnswer::create([
            'exam_result_id' => $result->id, // ✅ NOW IT EXISTS
            'exam_id' => $exam->id,
            'question_id' => $questionId,
            'student_id' => $studentId,
            'selected_answer' => $answer,
        ]);

        if ($question->correct_answer === $answer) {
            $score++;
        }
    }

    // ✅ UPDATE SCORE AFTER LOOP
    $percentage = ($score / $totalQuestions) * 100;
    $grade = $this->gradeResult($percentage);

    $result->update([
        'score' => $score,
    ]);

    // ✅ SAVE BADGES
    if ($grade['badges'] > 0) {
        StudentBadge::create([
            'student_id' => $studentId,
            'badges' => $grade['badges'],
            'source' => 'exam',
        ]);
    }

    return response()->json([
        'score' => $score,
        'percentage' => round($percentage, 1),
        'badges' => $grade['badges'],
        'result_id' => $result->id,
        'is_late' => $isLate,
    ]);
}


//create

private function gradeResult(float $percentage): array
{
    if ($percentage >= 75) {
        return ['label' => 'Excellent', 'badges' => 5];
    }

    if ($percentage >= 65) {
        return ['label' => 'Very Good', 'badges' => 3];
    }

    if ($percentage >= 50) {
        return ['label' => 'Good', 'badges' => 2];
    }

    if ($percentage >= 40) {
        return ['label' => 'Pass', 'badges' => 1];
    }

    return ['label' => 'Fail', 'badges' => 0];
}


public function block($id)
{
    $exam = Exam::where('id', $id)
        ->where('teacher_id', auth()->id())
        ->firstOrFail();

    $exam->update(['is_blocked' => true]);

    return response()->json([
        'message' => 'Exam blocked'
    ]);
}

public function unblock($id)
{
    $exam = Exam::where('id', $id)
        ->where('teacher_id', auth()->id())
        ->firstOrFail();

    $exam->update(['is_blocked' => false]);

    return response()->json([
        'message' => 'Exam unblocked'
    ]);
}




public function unreadCount()
{
    return Notification::where('user_id', auth()->id())
        ->whereNull('read_at')
        ->count();
}

//submit
public function submitByToken(Request $request, string $token)
{
    $exam = Exam::where('access_token', $token)
        ->with('questions')
        ->firstOrFail();

    return $this->submit($request, $exam);
}


public function start($token)
{
    $exam = Exam::where('access_token', $token)
        ->with('questions')
        ->firstOrFail();

     if ($exam->is_blocked) {
        return response()->json([
            'status' => 'blocked',
            'message' => 'Sorry, this exam link has been blocked. Please contact your teacher to unblock it or reshare another link.'
        ], 403);
    }


    $studentId = auth()->id();

    // 🔴 FINAL SUBMISSION CHECK (ONLY FINALIZED SUBMISSIONS)
    $submitted = ExamResult::where([
        'exam_id' => $exam->id,
        'student_id' => $studentId,
    ])->whereNotNull('submitted_at')->exists();

    if ($submitted) {
        return response()->json(['status' => 'submitted']);
    }

    // 🔵 EXISTING ATTEMPT
    $submission = ExamSubmission::where([
        'exam_id' => $exam->id,
        'student_id' => $studentId,
    ])->first();

    // 🔒 Expired but never started
    

    if (!$submission && now()->greaterThan($exam->due_at->endOfDay())) {
    return response()->json([
        'status' => 'expired_not_started',
        'exam_id' => $exam->id,
        'due_at' => $exam->due_at,
    ]);
}


    // ▶ Resume
    if ($submission) {
        return response()->json([
            'status' => 'in_progress',
            'exam' => $exam,
            'answers' => $submission->answers,
            'current_index' => $submission->current_index,
            'remaining_seconds' => $submission->remaining_seconds,
            'started_at' => $submission->started_at,
        ]);
    }

    \Log::info('Submission check', [
    'exists' => ExamResult::where([
        'exam_id' => $exam->id,
        'student_id' => auth()->id(),
    ])->exists()
]);


    // 🆕 Fresh access
    return response()->json([
        'status' => 'new',
        'exam' => $exam,
    ]);
}


public function begin($token)
{
    $exam = Exam::where('access_token', $token)->firstOrFail();
    $studentId = auth()->id();

    $submission = ExamSubmission::firstOrCreate(
        [
            'exam_id' => $exam->id,
            'student_id' => $studentId,
        ],
        [
            'remaining_seconds' => $exam->duration_minutes * 60,
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
                ?? $exam->duration_minutes * 60,
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
    return ExamSubmission::with('exam')
        ->where('student_id', auth()->id())
        ->whereNull('submitted_at') // ❌ hide submitted
        ->whereHas('exam', function ($q) {
            $q->where('due_at', '>', now()); // ❌ hide expired
        })
        ->latest()
        ->get();
}


public function saveProgress(Request $request)
{
    $request->validate([
        'exam_id' => 'required|exists:exams,id',
        'answers' => 'nullable|array',
        'current_index' => 'required|integer|min:0',
        'remaining_seconds' => 'required|integer|min:0',
    ]);

    $studentId = auth()->id();

    $submission = ExamSubmission::where([
        'exam_id' => $request->exam_id,
        'student_id' => $studentId,
    ])->first();

    // ❌ No submission → cannot save
    if (!$submission) {
        return response()->json([
            'error' => 'Exam not started',
        ], 403);
    }

    // ❌ Already submitted → lock
    if ($submission->submitted_at) {
        return response()->json([
            'error' => 'Exam already submitted',
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
    $exam = Exam::where('access_token', $token)->firstOrFail();

    $attempt = ExamSubmission::where([
        'exam_id' => $exam->id,
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

    $exam = Exam::where('access_token', $token)->firstOrFail();

    // 🔴 Block if already finally submitted
    $submitted = ExamResult::where([
        'exam_id' => $exam->id,
        'student_id' => $studentId,
    ])->exists();

    if ($submitted) {
        return response()->json([
            'message' => 'Exam already submitted'
        ], 409);
    }

    $submission = ExamSubmission::where([
        'exam_id' => $exam->id,
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
        'remaining_seconds' => $exam->duration_minutes * 60,
        'started_at' => null,
    ]);

    return response()->json([
        'message' => 'Exam restarted successfully'
    ]);
}



public function reschedule(Request $request)
{
    $request->validate([
        'exam_id' => 'required|exists:exams,id',
        'new_due_at' => 'required|date|after:now',
    ]);

    $studentId = auth()->id();
    $exam = Exam::findOrFail($request->exam_id);

    // ❌ Cannot reschedule after starting
    $started = ExamSubmission::where([
        'exam_id' => $exam->id,
        'student_id' => $studentId,
    ])->whereNotNull('started_at')->exists();

    if ($started) {
        return response()->json([
            'message' => 'Cannot reschedule after starting',
        ], 403);
    }

    $submission = ExamSubmission::firstOrCreate(
        [
            'exam_id' => $exam->id,
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

    $originalDue = Carbon::parse($exam->due_at);
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
        'user_id' => $exam->teacher_id,
        'type' => 'exam_rescheduled',
        'data' => [
            'exam_id' => $exam->id,
            'student_id' => $studentId,
            'old_due_at' => $originalDue,
            'new_due_at' => $requestedDate,
        ],
    ]);

    return response()->json([
        'message' => 'Exam rescheduled',
        'new_due_at' => $requestedDate,
        'locked' => true,
    ]);
}


}
