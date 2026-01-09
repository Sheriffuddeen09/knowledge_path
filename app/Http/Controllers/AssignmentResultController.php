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

    $query = AssignmentResult::with([
        'assignment.teacher',
        'student'
    ]);

    /**
     * STUDENT VIEW
     */
    if ($user->role === 'student') {
        $query
            ->where('hidden_for_student', false)
            ->whereHas('assignment', function ($q) use ($user) {
                $q->where('student_id', $user->id);
            });
    }

    /**
     * TEACHER VIEW
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

            // assignment
            'assignment_title' => $r->assignment->title,

            // Teacher name
            'teacher' => $r->assignment->teacher
                ? $r->assignment->teacher->first_name . ' ' . $r->assignment->teacher->last_name
                : null,

            // Student name
            'student' => $r->student
                ? $r->student->first_name . ' ' . $r->student->last_name
                : null,

            // Score
            'score' => $r->score,
            'total' => $r->total_questions,

            // Safe ratio
            'ratio' => $r->total_questions > 0
                ? round(($r->score / $r->total_questions) * 100, 1)
                : 0,

            'submitted_at' => $r->submitted_at,
        ];
    });
}






public function show(AssignmentResult $result)
{
    $result->load([
        'assignment.questions',
        'answers.question',
        'student',
        'assignment.teacher'
    ]);

    return response()->json($result);
}



}
