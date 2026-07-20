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
use App\Models\Notification;
use App\Models\Chat;
use Illuminate\Support\Facades\Log;
use App\Models\TeacherRequest;
use App\Models\LiveClassRequest;
use App\Models\TeacherReview;


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

    // 1️⃣ Create teacher profile
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

    // 2️⃣ Update teacher user info
    $user = auth()->user();
    $user->teacher_profile_completed = 1;
    $user->teacher_info = json_encode($teacherForm);
    $user->save();

    // 3️⃣ Notify all students about new teacher
    User::where('role', 'student')
        ->where('id', '!=', $user->id) // avoid notifying self
        ->get()
        ->each(function ($student) use ($user) {
            Notification::create([
                'user_id' => $student->id,
                'type' => 'teacher_suggestion',
                'data' => json_encode([
                    'teacher_name' => $user->first_name . ' ' . $user->last_name,
                    'teacher_id' => $user->id,
                ]),
                'redirect_url' => '/get-mentor',
                'read' => false,
            ]);
        });

    \Log::info('Teacher profile completed', [
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
    $user = auth()->user();

    $chatUserIds = Chat::where('type', 'private')
        ->where(function ($query) use ($user) {
            $query->where('user_one_id', $user->id)
                  ->orWhere('user_two_id', $user->id);
        })
        ->get()
        ->map(function ($chat) use ($user) {
            return $chat->user_one_id == $user->id
                ? $chat->user_two_id
                : $chat->user_one_id;
        
        })
        ->filter()
        ->unique();

    $teachers = User::where('teacher_profile_completed', 1)
        ->where('id', '!=', $user->id)
        ->whereNotIn('id', $chatUserIds)
        ->get();

    $teachers = $teachers->map(function ($teacher) {

        $info = json_decode($teacher->teacher_info, true) ?? [];

        $courseTitle = null;

        if (!empty($info['coursetitle_id'])) {
            $courseTitle = Coursetitle::find($info['coursetitle_id'])?->name;
        }

        $reviewCount = TeacherReview::where('teacher_id', $teacher->id)->count();

        $averageRating = round(
            TeacherReview::where('teacher_id', $teacher->id)
                ->avg('rating') ?? 0,
            1
        );
        $displayTitle = strtolower($courseTitle ?? '') === 'other'
            ? 'Other'
            : $courseTitle;

        return [
            'id' => $teacher->id,
            'first_name' => $teacher->first_name,
            'last_name' => $teacher->last_name,
            'location' => $teacher->location,
            'gender' => $teacher->gender,

            'logo' => isset($info['logo'])
                ? asset('storage/'.$info['logo'])
                : null,

            'cv' => isset($info['cv'])
                ? asset('storage/'.$info['cv'])
                : null,

            'coursetitle_id' => $info['coursetitle_id'] ?? null,
            'coursetitle_name' => $displayTitle,

            'specialization' => strtolower($courseTitle ?? '') === 'other'
                ? ($info['specialization'] ?? [])
                : [],

            'course_payment' => $info['course_payment'] ?? null,
            'currency' => $info['currency'] ?? null,
            'experience' => $info['experience'] ?? [],
            'qualification' => $info['qualification'] ?? [],
            'compliment' => $info['compliment'] ?? [],
            'average_rating' => $averageRating,
            'review_count' => $reviewCount,
        ];
    });

    return response()->json([
        'status' => true,
        'teachers' => $teachers,
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

    $form = $form->fresh();

        return response()->json([
            'status' => true,
            'message' => 'Teacher profile updated successfully',
            'data' => [
                'id' => $form->id,

                'coursetitle_id' => (int) $form->coursetitle_id,

                'qualification' => $form->qualification ?? [],
                'experience' => $form->experience ?? [],
                'specialization' => $form->specialization ?? [],
                'compliment' => $form->compliment ?? [],

                'course_payment' => $form->course_payment,
                'currency' => $form->currency,

                // ✅ full urls
                'logo' => $form->logo
                    ? asset('storage/' . $form->logo)
                    : null,

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

    $reviews = TeacherReview::with([
        'student:id,first_name,last_name'
    ])
    ->where('teacher_id', $user->id)
    ->latest()
    ->get();

    return response()->json([

        'status' => true,

        'teacher' => [

            'id' => $user->id,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'location' => $user->location,
            'gender' => $user->gender,

            'logo' => isset($info['logo'])
                ? asset('storage/'.$info['logo'])
                : null,

            'cv' => isset($info['cv'])
                ? asset('storage/'.$info['cv'])
                : null,

            'coursetitle_name' => $displayTitle,

            'specialization' => $info['specialization'] ?? [],

            'course_payment' => $info['course_payment'] ?? null,

            'currency' => $info['currency'] ?? null,

            'experience' => $info['experience'] ?? [],

            'qualification' => $info['qualification'] ?? [],

            'compliment' => $info['compliment'] ?? [],

            // Review summary
            'average_rating' => round(
                $reviews->avg('rating') ?? 0,
                1
            ),

            'review_count' => $reviews->count(),
        ],

        // Reviews
        'reviews' => $reviews->map(function ($review) {

            return [

                'id' => $review->id,

                'student_id' => $review->student->id,

                'first_name' => $review->student->first_name,

                'last_name' => $review->student->last_name,

                'avatar' => strtoupper(
                    substr($review->student->first_name, 0, 1)
                ),

                'rating' => $review->rating,

                'review' => $review->review,

                'created_at' => $review->created_at->diffForHumans(),

            ];

        }),

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


public function submitReview(Request $request)
{
    $request->validate([
        'teacher_id' => 'required|exists:users,id',
        'rating' => 'required|integer|min:1|max:5',
        'review' => 'nullable|string|max:1000',
    ]);

    $teacherRequest = TeacherRequest::where('teacher_id', $request->teacher_id)
        ->where('student_id', auth()->id())
        ->where('status', 'accepted')
        ->latest()
        ->first();

    $liveRequest = LiveClassRequest::where('teacher_id', $request->teacher_id)
        ->where('user_id', auth()->id())
        ->where('status', 'accepted')
        ->latest()
        ->first();

    if (!$teacherRequest && !$liveRequest) {
        return response()->json([
            'status' => false,
            'message' => 'You can only review teachers whose request has been accepted.'
        ], 403);
    }

    TeacherReview::updateOrCreate(
        [
            'teacher_request_id' => $teacherRequest?->id,
            'live_class_request_id' => $liveRequest?->id,
            'student_id' => auth()->id(),
        ],
        [
            'teacher_id' => $request->teacher_id,
            'rating' => $request->rating,
            'review' => $request->review,
        ]
    );

    return response()->json([
        'status' => true,
        'message' => 'Review submitted successfully.',
    ]);
}


public function teacherReviewHome(Request $request)
{
    $request->validate([
        'teacher_id' => 'required|exists:users,id',
    ]);

    $teacher = User::findOrFail($request->teacher_id);

    $reviews = TeacherReview::with([
        'student:id,first_name,last_name'
    ])
    ->where('teacher_id', $teacher->id)
    ->latest()
    ->get();

    return response()->json([
        'status' => true,

        'teacher' => [
            'id' => $teacher->id,
            'name' => $teacher->first_name.' '.$teacher->last_name,
        ],

        'average_rating' => round($reviews->avg('rating') ?? 0, 1),

        'review_count' => $reviews->count(),

        'reviews' => $reviews->map(function ($review) {

            return [

                'id' => $review->id,

                'student_id' => $review->student->id,

                'first_name' => $review->student->first_name,

                'last_name' => $review->student->last_name,

                'avatar' => strtoupper(substr($review->student->first_name,0,1)),

                'rating' => $review->rating,

                'review' => $review->review,

                'created_at' => $review->created_at->diffForHumans(),

            ];

        }),

    ]);
}

public function teacherReviews($teacher_id)
{
    $teacher = User::findOrFail($teacher_id);

    $reviews = TeacherReview::with('student:id,first_name,last_name')
        ->where('teacher_id', $teacher->id)
        ->latest()
        ->get();

    return response()->json([
        'status' => true,
        'teacher' => [
            'id' => $teacher->id,
            'name' => $teacher->first_name . ' ' . $teacher->last_name,
        ],
        'average_rating' => round($reviews->avg('rating') ?? 0, 1),
        'review_count' => $reviews->count(),
        'reviews' => $reviews->map(function ($review) {
            return [
                'id' => $review->id,
                'student_id' => $review->student->id,
                'first_name' => $review->student->first_name,
                'last_name' => $review->student->last_name,
                'avatar' => strtoupper(substr($review->student->first_name, 0, 1)),
                'rating' => $review->rating,
                'review' => $review->review,
                'created_at' => $review->created_at->diffForHumans(),
            ];
        }),
    ]);
}

public function reviewNotification()
{
    $teacher = auth()->user();

    if ($teacher->role !== 'admin') {
        return response()->json([
            'pending_reviews' => 0
        ]);
    }

    $count = TeacherReview::where('teacher_id', $teacher->id)
        ->where('is_read', false)
        ->count();

    return response()->json([
        'pending_reviews' => $count
    ]);
}


public function acceptedTeachers(Request $request)
{
    $student = $request->user();

    $teachers = collect();

    $teacherRequests = TeacherRequest::with([
        'teacher',
        'teacherForm.courseTitle',
    ])
    ->where('student_id', $student->id)
    ->where('status', 'accepted')
    ->get();

    foreach ($teacherRequests as $requestItem) {

        $teacherForm = $requestItem->teacherForm;

        $courseTitle = $teacherForm?->courseTitle?->name;

        $review = TeacherReview::where('teacher_request_id', $requestItem->id)
            ->where('student_id', $student->id)
            ->first();

        $teachers->push([

            'type' => 'teacher_request',

            'request_id' => $requestItem->id,

            'teacher_id' => $requestItem->teacher->id,

            'first_name' => $requestItem->teacher->first_name,

            'last_name' => $requestItem->teacher->last_name,

            'gender' => $requestItem->teacher->gender,

            'location' => $requestItem->teacher->location,

            'logo' => $teacherForm?->logo
                ? asset('storage/'.$teacherForm->logo)
                : null,

            'coursetitle_name' => strtolower($courseTitle ?? '') === 'other'
                ? 'Other'
                : $courseTitle,

            'specialization' => strtolower($courseTitle ?? '') === 'other'
                ? ($teacherForm?->specialization ?? [])
                : [],

            'qualification' => $teacherForm?->qualification,

            'experience' => $teacherForm?->experience,

            'course_payment' => $teacherForm?->course_payment,

            'currency' => $teacherForm?->currency,

            'compliment' => $teacherForm?->compliment,

            'already_reviewed' => $review !== null,

            'review' => $review,
        ]);
    }

    $liveRequests = LiveClassRequest::with('teacher')
        ->where('user_id', $student->id)
        ->where('status', 'accepted')
        ->get();

    foreach ($liveRequests as $requestItem) {

        // Skip duplicate teacher
        if ($teachers->contains('teacher_id', $requestItem->teacher_id)) {
            continue;
        }

        $info = json_decode($requestItem->teacher->teacher_info, true) ?? [];

        $courseTitle = null;

        if (!empty($info['coursetitle_id'])) {
            $courseTitle = Coursetitle::find($info['coursetitle_id'])?->name;
        }

        $displayTitle = strtolower($courseTitle ?? '') === 'other'
            ? 'Other'
            : $courseTitle;

        $review = TeacherReview::where('live_class_request_id', $requestItem->id)
            ->where('student_id', $student->id)
            ->first();

        $teachers->push([

            'type' => 'live_class_request',

            'request_id' => $requestItem->id,

            'teacher_id' => $requestItem->teacher->id,

            'first_name' => $requestItem->teacher->first_name,

            'last_name' => $requestItem->teacher->last_name,

            'gender' => $requestItem->teacher->gender,

            'location' => $requestItem->teacher->location,

            'logo' => !empty($info['logo'])
                ? asset('storage/'.$info['logo'])
                : null,

            'coursetitle_name' => $displayTitle,

            'specialization' => strtolower($courseTitle ?? '') === 'other'
                ? ($info['specialization'] ?? [])
                : [],

            'qualification' => $info['qualification'] ?? null,

            'experience' => $info['experience'] ?? null,

            'course_payment' => $info['course_payment'] ?? null,

            'currency' => $info['currency'] ?? null,

            'compliment' => $info['compliment'] ?? null,

            'already_reviewed' => $review !== null,

            'review' => $review,
        ]);
    }

    return response()->json([
        'status' => true,
        'teachers' => $teachers->values(),
    ]);
}
}

