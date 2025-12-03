<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\TeacherForm;
use Illuminate\Support\Facades\Storage;

class TeacherFormController extends Controller
{
    /**
     * Store teacher form details
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'qualification' => 'required|string|max:255',
            'experience' => 'required|string|max:255',
            'specialization' => 'nullable|string|max:255',
            'course_title' => 'required|string|max:255',
            'course_payment' => 'required|numeric',
            'currency' => 'required|string|max:10',
            'compliment' => 'nullable|string|max:255',
            'logo' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'cv' => 'nullable|file|mimes:pdf,doc,docx|max:5120',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Handle file uploads
        $logoPath = $request->hasFile('logo') ? $request->file('logo')->store('teacher_logos', 'public') : null;
        $cvPath = $request->hasFile('cv') ? $request->file('cv')->store('teacher_cvs', 'public') : null;

        $teacherForm = TeacherForm::create([
            'user_id' => $request->user()->id,
            'qualification' => $request->qualification,
            'experience' => $request->experience,
            'specialization' => $request->specialization,
            'course_title' => $request->course_title,
            'course_payment' => $request->course_payment,
            'currency' => $request->currency,
            'compliment' => $request->compliment ?? null,
            'logo' => $logoPath,
            'cv' => $cvPath,
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Teacher form submitted successfully',
            'teacherForm' => $teacherForm
        ]);
    }
}
