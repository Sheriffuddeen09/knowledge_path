<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Http\Request;


class AnalyticsController extends Controller
{
    public function studentAnalytics()
{
    $students = DB::table('users')
        ->where('role', 'student')
        ->leftJoin('assignment_results', 'users.id', '=', 'assignment_results.student_id')
        ->leftJoin('exam_results', 'users.id', '=', 'exam_results.student_id')
        ->select(
            'users.id',
            'users.first_name',
            'users.last_name',

            DB::raw('
                AVG(
                    assignment_results.score * 100.0 /
                    NULLIF(assignment_results.total_questions, 0)
                ) as assignment_avg
            '),

            DB::raw('
                AVG(
                    exam_results.score * 100.0 /
                    NULLIF(exam_results.total_questions, 0)
                ) as exam_avg
            '),

            DB::raw('(
                SELECT COALESCE(SUM(badges),0)
                FROM student_badges
                WHERE student_badges.student_id = users.id
            ) as total_badges')
        )
        ->groupBy('users.id', 'users.first_name', 'users.last_name')
        ->get()
        ->map(function ($s) {
            $a = $s->assignment_avg ?? 0;
            $e = $s->exam_avg ?? 0;

            $s->avg_score = round(($a + $e) / 2, 2);
            $s->name = "{$s->first_name} {$s->last_name}";

            return $s;
        })
        ->sortByDesc('avg_score')
        ->values()
        ->map(function ($s, $index) {
            $s->rank = $index + 1;
            return $s;
        });

    return response()->json($students);
}



public function performanceSplit(Request $request)
{
    $userId = $request->user()->id;

    return response()->json([
        'assignments' => DB::table('assignment_results')
            ->where('student_id', $userId)
            ->avg(DB::raw('score * 100.0 / NULLIF(total_questions, 0)')),

        'exams' => DB::table('exam_results')
            ->where('student_id', $userId)
            ->avg(DB::raw('score * 100.0 / NULLIF(total_questions, 0)')),
    ]);
}


   
    public function accuracy(Request $request)
{
    $userId = $request->user()->id;

    return response()->json([
        'assignment_accuracy' => DB::table('assignment_results')
            ->where('student_id', $userId)
            ->avg(DB::raw('score * 100.0 / NULLIF(total_questions, 0)')),

        'exam_accuracy' => DB::table('exam_results')
            ->where('student_id', $userId)
            ->avg(DB::raw('score * 100.0 / NULLIF(total_questions, 0)')),
    ]);
}

public function performanceSplitId(Request $request)
{
    $userId = $request->student_id ?? $request->user()->id;

    return response()->json([
        'assignments' => DB::table('assignment_results')
            ->where('student_id', $userId)
            ->avg(DB::raw('score * 100.0 / total_questions')) ?? 0,

        'exams' => DB::table('exam_results')
            ->where('student_id', $userId)
            ->avg(DB::raw('score * 100.0 / total_questions')) ?? 0,
    ]);
}

public function accuracyId(Request $request)
{
    $userId = $request->student_id ?? $request->user()->id;

    return response()->json([
        'assignment_accuracy' => DB::table('assignment_results')
            ->where('student_id', $userId)
            ->avg(DB::raw('score * 100.0 / total_questions')) ?? 0,

        'exam_accuracy' => DB::table('exam_results')
            ->where('student_id', $userId)
            ->avg(DB::raw('score * 100.0 / total_questions')) ?? 0,
    ]);
}

}
