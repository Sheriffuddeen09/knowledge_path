<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\TeacherForm;
use Illuminate\Support\Facades\Storage;
use App\Rules\MaxWords;

class TeacherFormController extends Controller
{
    /**
     * Store teacher form details
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'qualification' => 'required|string|max:255',
            'experience' => 'nullable|string|max:255',
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

public function submitForm(Request $request)
{
    $validator = Validator::make($request->all(), [
        'qualification' => ['required', new MaxWords(755)],
        'experience' => 'nullable|string|max:255',
        'specialization' => 'nullable|string|max:255',
        'course_title' => 'required|string|max:255',
        'course_payment' => 'required|numeric',
        'currency' => 'required|string|max:10',
        'compliment' => ['nullable', new MaxWords(755)],
        'logo' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        'cv' => 'nullable|file|mimes:pdf,doc,docx|max:5120',
    ]);

    if ($validator->fails()) {
        return response()->json(['errors' => $validator->errors()], 422);
    }

    // Handle file uploads
    $logoPath = $request->hasFile('logo') 
        ? $request->file('logo')->store('teacher_logos', 'public') 
        : null;

    $cvPath = $request->hasFile('cv') 
        ? $request->file('cv')->store('teacher_cvs', 'public') 
        : null;

    // Save to teacher_forms table
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

    // Update the user record
    $user = $request->user();
    $user->teacher_info = json_encode($teacherForm); // optional: store the form info
    $user->teacher_profile_completed = 1;
    $user->save();

    return response()->json([
        'status' => true,
        'message' => 'Teacher form submitted successfully',
        'teacherForm' => $teacherForm
    ]);
}

    // TeacherFormController.php
    public function getTeacherForm()
{
    $user = auth()->user();

    $teacherForm = $user ? json_decode($user->teacher_info, true) : null;

    if ($teacherForm) {
        // Convert logo and cv to full URLs
        $teacherForm['logo'] = isset($teacherForm['logo']) ? asset('storage/' . $teacherForm['logo']) : null;
        $teacherForm['cv'] = isset($teacherForm['cv']) ? asset('storage/' . $teacherForm['cv']) : null;
    }

    return response()->json([
        'status' => true,
        'teacherForm' => $teacherForm
    ]);
}



    // TeacherFormController.php
public function allTeachers()
{
    // Fetch all users with teacher_profile_completed = 1
    $teachers = \App\Models\User::where('teacher_profile_completed', 1)->get();

    // Decode teacher_info JSON for each teacher
    $teachers = $teachers->map(function($user) {
        $info = json_decode($user->teacher_info, true);
        return [
            'id' => $user->id,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'logo' => isset($info['logo']) ? asset('storage/' . $info['logo']) : null,
            'cv' => isset($info['cv']) ? asset('storage/' . $info['cv']) : null,
            'course_title' => $info['course_title'] ?? 'Arabic Course',
            'course_payment' => $info['course_payment'] ?? 'N/A',
            'currency' => $info['currency'] ?? '$',
            'compliment' => $info['compliment'] ?? '',
            'qualification' => $info['qualification'] ?? '',
            'experience' => $info['experience'] ?? '',

        ];
    });

    
    return response()->json([
        'status' => true,
        'teachers' => $teachers
    ]);
}

}
