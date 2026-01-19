<?php

namespace App\Http\Controllers;

use App\Models\StudentBadge;
use Illuminate\Http\Request;

class StudentBadgeController extends Controller
{
    public function badges(Request $request)
    {
        $studentId = $request->user()->id;

        return response()->json([
            'total' => StudentBadge::where('student_id', $studentId)->sum('badges'),

            'assignment' => StudentBadge::where('student_id', $studentId)
                ->where('source', 'assignment')
                ->sum('badges'),

            'exam' => StudentBadge::where('student_id', $studentId)
                ->where('source', 'exam')
                ->sum('badges'),
        ]);
    }

    

}
