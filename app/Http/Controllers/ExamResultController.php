<?php

namespace App\Http\Controllers;

use App\Models\ExamResult;
use Barryvdh\DomPDF\Facade\Pdf;

class ExamResultController extends Controller
{
    // 📌 GET RESULTS
  public function index()
{
    $user = auth()->user();

    $expiryDate = now()->subDays(30);

    $query = ExamResult::with([
        'exam.teacher',
        'student'
    ])
    // ✅ GLOBAL RULE: hide results older than 1 days
    ->where('submitted_at', '>=', $expiryDate);

    /**
     * 🧑‍🎓 STUDENT VIEW
     */
    if ($user->role === 'student') {
        $query
            ->where('hidden_for_student', false)
            ->where('student_id', $user->id);
    }

    /**
     * 👨‍🏫 TEACHER VIEW
     */
    if ($user->role === 'teacher') {
        $query
            ->where('hidden_for_teacher', false)
            ->whereHas('exam', function ($q) use ($user) {
                $q->where('teacher_id', $user->id);
            });
    }

    $results = $query->latest()->get();

    return $results->map(function ($r) {
        return [
            'id' => $r->id,

            'exam_title' => optional($r->exam)->title,

            'teacher' => optional($r->exam->teacher)
                ? $r->exam->teacher->first_name . ' ' . $r->exam->teacher->last_name
                : null,

            'student' => optional($r->student)
                ? $r->student->first_name . ' ' . $r->student->last_name
                : null,

            'score' => $r->score,
            'total' => $r->total_questions,

            'ratio' => $r->total_questions > 0
                ? round(($r->score / $r->total_questions) * 100, 1)
                : 0,

            'submitted_at' => $r->submitted_at,
        ];
    });
}

public function show(ExamResult $result)
{
    // ❌ Block expired results
    if ($result->submitted_at < now()->subDays(30)) {
        abort(404, 'This result has expired');
    }

    $result->load([
        'exam.questions',
        'answers.question',
        'student',
        'exam.teacher'
    ]);

    return response()->json($result);
}
}
