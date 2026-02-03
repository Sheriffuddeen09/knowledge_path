<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\Coursetitle;
use App\Models\TeacherForm;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use App\Rules\MaxWords;
 use Illuminate\Validation\Rule;
 use Illuminate\Support\Facades\Auth;

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
        'qualification' => $request->qualification,
        'experience' => $request->experience,
        'specialization' => $request->specialization,
        'course_payment' => $request->course_payment,
        'currency' => $request->currency,
        'compliment' => $request->compliment,
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

    $info = json_decode($user->teacher_info, true) ?? [];

    // Fetch the actual course title
    $courseTitle = null;
    if (!empty($info['coursetitle_id'])) {
        $courseTitle = \App\Models\Coursetitle::find($info['coursetitle_id'])?->name ?? null;
    }

    // If course is "Other", set display title to "Other" and keep specialization as entered
    $displayTitle = $courseTitle;
    if (strtolower($courseTitle ?? '') === 'other') {
        $displayTitle = 'Other';
    }

    return [
        'id' => $user->id,
        'first_name' => $user->first_name,
        'last_name' => $user->last_name,
        'location' => $user->location,
        'gender' => $user->gender,
        'logo' => isset($info['logo']) ? asset('storage/' . $info['logo']) : null,
        'cv' => isset($info['cv']) ? asset('storage/' . $info['cv']) : null,

        'coursetitle_id' => $info['coursetitle_id'] ?? null,
        'coursetitle_name' => $displayTitle,
        'specialization' => strtolower($courseTitle ?? '') === 'other' ? ($info['specialization'] ?? []) : [],

        'course_payment' => $info['course_payment'] ?? null,
        'currency' => $info['currency'] ?? null,
        'experience' => $info['experience'] ?? [],
        'qualification' => $info['qualification'] ?? [],
        'compliment' => $info['compliment'] ?? [],
    ];
});


    return response()->json([
        'status' => true,
        'teachers' => $teachers
    ]);
}


public function update(Request $request)
{
    $user = $request->user();

    // Get the teacher form
    $form = TeacherForm::where('user_id', $user->id)->firstOrFail();

    // Get the ID of the "Other" course title
    $otherId = Coursetitle::whereRaw('LOWER(name) = ?', ['other'])->value('id');

    // Validate request
    $validator = Validator::make($request->all(), [
        'coursetitle_id' => 'required|exists:coursetitles,id',

        'qualification' => 'required|array',
        'qualification.*' => ['string', new MaxWords(755)],

        'experience' => 'nullable|array',
        'experience.*' => 'string|max:255',

        'specialization' => [
            Rule::requiredIf(fn () => (int)$request->coursetitle_id === (int)$otherId),
            'array',
        ],
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

    // Update teacher form
    $form->update([
        'coursetitle_id' => (int)$request->coursetitle_id,
        'qualification' => $request->qualification,
        'experience' => $request->experience ?? [],
        'specialization' => $request->specialization ?? [],
        'compliment' => $request->compliment ?? [],
        'course_payment' => $request->course_payment,
        'currency' => $request->currency,
    ]);

    // Handle files
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

    // 🔹 Sync back to users.teacher_info
    $user->update([
        'teacher_info' => json_encode([
            'coursetitle_id' => (int)$form->coursetitle_id,
            'qualification' => $form->qualification ?? [],
            'experience' => $form->experience ?? [],
            'specialization' => $form->specialization ?? [],
            'compliment' => $form->compliment ?? [],
            'course_payment' => $form->course_payment,
            'currency' => $form->currency,
            'logo' => $form->logo,
            'cv' => $form->cv,
        ])
    ]);

    return response()->json([
        'status' => true,
        'message' => 'Teacher profile updated successfully',
        'data' => [
            'coursetitle_id' => (int)$form->coursetitle_id,
            'qualification' => $form->qualification ?? [],
            'experience' => $form->experience ?? [],
            'specialization' => $form->specialization ?? [],
            'compliment' => $form->compliment ?? [],
            'course_payment' => $form->course_payment,
            'currency' => $form->currency,
            'logo' => $form->logo,
            'cv' => $form->cv,
        ]
    ]);
}


public function myTeacherProfile($id)
{
    $user = User::where('id', $id)
        ->where('admin_choice', 'arabic_teacher')
        ->firstOrFail();

    $info = json_decode($user->teacher_info, true) ?? [];

    $courseTitle = null;
    if (!empty($info['coursetitle_id'])) {
        $courseTitle = \App\Models\Coursetitle::find($info['coursetitle_id'])?->name;
    }

    $displayTitle = strtolower($courseTitle ?? '') === 'other'
        ? 'Other'
        : $courseTitle;

    return response()->json([
        'status' => true,
        'teacher' => [
            'id' => $user->id,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'location' => $user->location,
            'gender' => $user->gender,

            // ❌ private info excluded
            // 'email' => ❌
            // 'phone' => ❌

            'logo' => isset($info['logo']) ? asset('storage/'.$info['logo']) : null,
            'cv' => isset($info['cv']) ? asset('storage/'.$info['cv']) : null,

            'coursetitle_name' => $displayTitle,
            'specialization' => $info['specialization'] ?? [],
            'course_payment' => $info['course_payment'] ?? null,
            'currency' => $info['currency'] ?? null,
            'experience' => $info['experience'] ?? [],
            'qualification' => $info['qualification'] ?? [],
            'compliment' => $info['compliment'] ?? [],
        ]
    ]);
}



public function singleTeachers()
{
    $user = Auth::user();

    if (!$user || $user->admin_choice !== 'arabic_teacher') {
        return response()->json([
            'status' => false,
            'message' => 'Unauthorized'
        ], 403);
    }

    $info = json_decode($user->teacher_info, true) ?? [];

    $courseTitle = null;
    if (!empty($info['coursetitle_id'])) {
        $courseTitle = \App\Models\Coursetitle::find($info['coursetitle_id'])?->name;
    }

    $displayTitle = strtolower($courseTitle ?? '') === 'other'
        ? 'Other'
        : $courseTitle;

    return response()->json([
        'status' => true,
        'teacher' => [
            'id' => $user->id,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'location' => $user->location,
            'gender' => $user->gender,

            // private info excluded
            // 'email' => ❌
            // 'phone' => ❌

            'logo' => isset($info['logo']) ? asset('storage/'.$info['logo']) : null,
            'cv' => isset($info['cv']) ? asset('storage/'.$info['cv']) : null,

            'coursetitle_name' => $displayTitle,
            'specialization' => $info['specialization'] ?? [],
            'course_payment' => $info['course_payment'] ?? null,
            'currency' => $info['currency'] ?? null,
            'experience' => $info['experience'] ?? [],
            'qualification' => $info['qualification'] ?? [],
            'compliment' => $info['compliment'] ?? [],
        ]
    ]);
}


}
