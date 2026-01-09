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

    $query = ExamResult::with([
        'exam.teacher',
        'student'
    ]);

    /**
     * STUDENT VIEW
     */
    if ($user->role === 'student') {
        $query
            ->where('hidden_for_student', false)
            ->whereHas('exam', function ($q) use ($user) {
                $q->where('student_id', $user->id);
            });
    }

    /**
     * TEACHER VIEW
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

            // Exam
            'exam_title' => $r->exam->title,

            // Teacher name
            'teacher' => $r->exam->teacher
                ? $r->exam->teacher->first_name . ' ' . $r->exam->teacher->last_name
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



public function show(ExamResult $result)
{
    $result->load([
        'exam.questions',
        'answers.question',
        'student',
        'exam.teacher'
    ]);

    return response()->json($result);
}


    public function destroy(ExamResult $result)
    {
        $user = auth()->user();

        // STUDENT DELETE
        if ($user->role === 'student') {
            abort_if($result->student_id !== $user->id, 403);

            $result->update([
                'hidden_for_student' => true
            ]);

            return response()->json([
                'message' => 'Result removed from your view'
            ]);
        }

        // TEACHER DELETE
        if ($user->role === 'teacher') {
            abort_if(
                $result->exam->teacher_id !== $user->id,
                403
            );

            $result->update([
                'hidden_for_teacher' => true
            ]);

            return response()->json([
                'message' => 'Result removed from your view'
            ]);
        }

        abort(403);
    }

public function downloadPdf(AssignmentResult $result)
{
    try {
        $result->load([
            'student',
            'assignment.teacher',
            'assignment.questions',
            'answers.question',
        ]);

        abort_if(
            auth()->id() !== $result->student_id &&
            auth()->id() !== optional($result->assignment)->teacher_id,
            403,
            'You cannot download this result'
        );

        return Pdf::loadView('pdf.assignment-result', [
            'result' => $result
        ])->download('assignment-result.pdf');

    } catch (\Throwable $e) {
        \Log::error('PDF generation failed', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);

        return response()->json([
            'error' => 'PDF generation failed',
            'message' => $e->getMessage(),
        ], 500);
    }
}

}
