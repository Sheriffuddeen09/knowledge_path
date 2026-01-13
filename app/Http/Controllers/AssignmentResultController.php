<?php

namespace App\Http\Controllers;

use App\Models\AssignmentResult;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Log;


class AssignmentResultController extends Controller
{
    // 📌 GET RESULTS

public function index()
{
    $user = auth()->user();

    $expiryDate = now()->subDays(30);

    $query = AssignmentResult::with([
        'assignment.teacher',
        'student'
    ])
    // ✅ GLOBAL RULE: hide results older than 30 days
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
            ->whereHas('assignment', function ($q) use ($user) {
                $q->where('teacher_id', $user->id);
            });
    }

    $results = $query->latest()->get();

    return $results->map(function ($r) {
        return [
            'id' => $r->id,

            'assignment_title' => optional($r->assignment)->title,

            'teacher' => optional($r->assignment->teacher)
                ? $r->assignment->teacher->first_name . ' ' . $r->assignment->teacher->last_name
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

public function show(AssignmentResult $result)
{
    // ❌ Block expired results
    if ($result->submitted_at < now()->subDays(30)) {
        abort(404, 'This result has expired');
    }

    $result->load([
        'assignment.questions',
        'answers.question',
        'student',
        'assignment.teacher'
    ]);

    return response()->json($result);
}

}
