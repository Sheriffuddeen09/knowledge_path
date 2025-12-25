<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\Coursetitle;
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
    $otherId = Coursetitle::whereRaw('LOWER(name) = ?', ['other'])->value('id');

    $validator = Validator::make($request->all(), [
        'coursetitle_id' => 'required|exists:coursetitles,id',
        'qualification' => ['required', new MaxWords(755)],
        'experience' => 'nullable|string|max:255',
        'specialization' => 'required_if:coursetitle_id,' . $otherId . '|nullable|string|max:255',
        'course_payment' => 'required|numeric',
        'currency' => 'required|string|max:10',
        'compliment' => ['nullable', new MaxWords(755)],
        'logo' => 'nullable|image|max:2048',
        'cv' => 'nullable|file|max:5120',
    ]);

    if ($validator->fails()) {
        return response()->json(['errors' => $validator->errors()], 422);
    }

    $teacherForm = TeacherForm::create([
        'user_id' => $request->user()->id,
        'coursetitle_id' => $request->coursetitle_id,
        'qualification' => json_decode($info['qualification'] ?? '[]', true),
        'experience'    => json_decode($info['experience'] ?? '[]', true),
        'specialization'=> json_decode($info['specialization'] ?? '[]', true),
        'compliment'    => json_decode($info['compliment'] ?? '[]', true),
        'course_payment' => $request->course_payment,
        'currency' => $request->currency,
        'logo' => $request->file('logo')?->store('teacher_logos', 'public'),
        'cv' => $request->file('cv')?->store('teacher_cvs', 'public'),
    ]);



    $user = auth()->user();

    $user->teacher_profile_completed = 1;
    $user->teacher_info = json_encode($teacherForm);
    $user->save();

    \Log::info('Teacher profile marked completed', [
    'user_id' => $user->id,
    'completed' => $user->teacher_profile_completed
]);



    return response()->json([
        'status' => true,
        'message' => 'Teacher profile completed successfully',
    ]);
}

    public function allTeachers()
{
    $teachers = \App\Models\User::where('teacher_profile_completed', 1)->get();

    $teachers = $teachers->map(function ($user) {

        // ✅ DEFINE $info FIRST
        $info = json_decode($user->teacher_info, true) ?? [];

        return [
            'id' => $user->id,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'gender' => $user->gender,
            'location' => $user->location,

            // ✅ Assets
            'logo' => isset($info['logo'])
                ? asset('storage/' . $info['logo'])
                : null,

            'cv' => isset($info['cv'])
                ? asset('storage/' . $info['cv'])
                : null,

            // ✅ Course data
            'coursetitle_id' => $info['coursetitle_id'] ?? null,
            'coursetitle_name' => json_decode($info['specialization'] ?? '[]', true)
                ?? $info['coursetitle_name']
                ?? 'Arabic Course',

            'course_payment' => $info['course_payment'] ?? null,
            'currency' => $info['currency'] ?? null,
            'qualification' => json_decode($info['qualification'] ?? '[]', true),
            'experience'    => json_decode($info['experience'] ?? '[]', true),
            'compliment'    => json_decode($info['compliment'] ?? '[]', true),

        ];
    });

    return response()->json([
        'status' => true,
        'teachers' => $teachers
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




public function show(Request $request)
{
    $form = TeacherForm::where('user_id', $request->user()->id)->first();

    return response()->json([
        'status' => true,
        'data' => $form ? [
            'coursetitle_id' => $form->coursetitle_id,
            'qualification' => $form->qualification,
            'experience' => $form->experience,
            'specialization' => $form->specialization,
            'compliment' => $form->compliment,
            'course_payment' => $form->course_payment,
            'currency' => $form->currency,
        ] : null
    ]);
}


 public function update(Request $request)
{
    $form = TeacherForm::where('user_id', $request->user()->id)->firstOrFail();

    $otherId = Coursetitle::whereRaw('LOWER(name) = ?', ['other'])->value('id');

    $validator = Validator::make($request->all(), [
        'coursetitle_id' => 'required|exists:coursetitles,id',

        'qualification' => 'required|array',
        'qualification.*' => ['string', new MaxWords(755)],

        'experience' => 'nullable|array',
        'experience.*' => 'string|max:255',

        'specialization' => 'required_if:coursetitle_id,' . $otherId . '|nullable|array',
        'specialization.*' => 'string|max:255',

        'course_payment' => 'required|numeric',
        'currency' => 'required|string|max:10',

        'compliment' => 'nullable|array',
        'compliment.*' => ['string', new MaxWords(755)],

        'logo' => 'nullable|image|max:2048',
        'cv' => 'nullable|file|max:5120',
    ]);

    if ($validator->fails()) {
        return response()->json(['errors' => $validator->errors()], 422);
    }

    $form->update([
        'coursetitle_id' => $request->coursetitle_id,
        'qualification' => $request->qualification,
        'experience' => $request->experience,
        'specialization' => $request->specialization,
        'compliment' => $request->compliment,
        'course_payment' => $request->course_payment,
        'currency' => $request->currency,
    ]);

    if ($request->hasFile('logo')) {
        $form->update([
            'logo' => $request->file('logo')->store('teacher_logos', 'public')
        ]);
    }

    if ($request->hasFile('cv')) {
        $form->update([
            'cv' => $request->file('cv')->store('teacher_cvs', 'public')
        ]);
    }

    return response()->json([
        'status' => true,
        'message' => 'Teacher profile updated successfully',
        'data' => $form
    ]);
}

}
