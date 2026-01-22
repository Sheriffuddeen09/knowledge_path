<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class AnalyticsController extends Controller
{
    public function studentAnalytics()
    {
    return DB::table('users')
    ->where('role', 'student')
    ->leftJoin('assignment_results', 'users.id', '=', 'assignment_results.student_id')
    ->leftJoin('exam_results', 'users.id', '=', 'exam_results.student_id')
    ->leftJoin('student_badges', 'users.id', '=', 'student_badges.student_id')
    ->select(
    'users.id',
    DB::raw("users.first_name || ' ' || users.last_name as name"),
    DB::raw("AVG(assignment_results.score * 100.0 / assignment_results.total_questions) as assignment_avg"),
    DB::raw("AVG(exam_results.score * 100.0 / exam_results.total_questions) as exam_avg"),
    DB::raw("AVG(assignment_results.score * 100.0 / assignment_results.total_questions) as avg_score"),
    DB::raw('(SELECT COALESCE(SUM(badges),0) FROM student_badges WHERE student_badges.student_id = users.id) as total_badges')

    )
    ->groupBy('users.id')
    ->get();
    }




    public function performanceSplit()
    {
        return [
            'assignments' => DB::table('assignment_results')
                ->avg(DB::raw('score * 100.0 / total_questions')),

            'exams' => DB::table('exam_results')
                ->avg(DB::raw('score * 100.0 / total_questions')),
        ];
    }

   
    public function accuracy()
    {
        return [
            'assignment_accuracy' => DB::table('assignment_results')
                ->avg(DB::raw('score * 100.0 / total_questions')),

            'exam_accuracy' => DB::table('exam_results')
                ->avg(DB::raw('score * 100.0 / total_questions')),
        ];
    }
}
