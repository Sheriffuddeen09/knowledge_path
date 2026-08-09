<?php

namespace App\Http\Controllers;

use App\Models\JobPost;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\JobCategory;
use Illuminate\Support\Str;
use App\Models\JobSkill;
use Illuminate\Support\Facades\Mail;
use App\Mail\JobPostPendingApprovalMail;
use Carbon\Carbon;
use App\Mail\JobApprovedMail;
use App\Mail\JobDeclinedMail;
use App\Models\JobApplication;

class JobPostController extends Controller
{

public function index(Request $request)
{
    $user = $request->user();

    $query = JobPost::with([
        'category',
        'user:id,first_name,last_name,email',
        'user.jobProfile'
    ])
    ->where('status', 'accepted')
    ->whereNull('deleted_at')
    ->whereDate('expire_date', '>=', now())

    // Hide jobs the current user has already applied for
    ->whereDoesntHave('applications', function ($q) use ($user) {

        $q->where('user_id', $user->id)
          ->whereIn('status', [
              'pending',
              'accepted',
              'reviewed',
              'rejected',
          ]);

    });

    if ($request->filled('search')) {

        $search = $request->search;

        $query->where(function ($q) use ($search) {

            $q->where('title', 'like', "%{$search}%")
              ->orWhere('description', 'like', "%{$search}%")
              ->orWhere('location', 'like', "%{$search}%")
              ->orWhere('about_us', 'like', "%{$search}%")
              ->orWhere('what_you_do', 'like', "%{$search}%");

        });

    }

    if ($request->filled('category')) {

        $query->where(
            'job_category_id',
            $request->category
        );

    }

    if ($request->filled('job_type')) {

        $query->where(
            'job_type',
            $request->job_type
        );

    }

    if ($request->filled('location')) {

        $query->where(
            'location',
            'like',
            '%' . $request->location . '%'
        );

    }

    if ($request->filled('min_salary')) {

        $query->where(
            'payment',
            '>=',
            $request->min_salary
        );

    }


    if ($request->filled('max_salary')) {

        $query->where(
            'payment',
            '<=',
            $request->max_salary
        );

    }

    switch ($request->sort) {

        case 'salary_high':

            $query->orderByDesc('payment');

            break;


        case 'salary_low':

            $query->orderBy('payment');

            break;


        case 'oldest':

            $query->oldest();

            break;


        case 'most_viewed':

            $query->orderByDesc('views');

            break;


        default:

            $query->latest();

            break;

    }


    $jobs = $query->paginate(12);


    return response()->json([

        'success' => true,

        'jobs' => $jobs,

    ]);
}


public function myPostedJobs(Request $request)
{
    $user = $request->user();

    $jobs = JobPost::with([
        'category',
        'user.jobProfile',
    ])
    ->withCount('applications')
    ->where('user_id', $user->id)
    ->latest()
    ->paginate(10);

    return response()->json([
        'success' => true,
        'jobs' => $jobs,
    ]);
}


public function destroyMyPostedJob(
    Request $request,
    JobPost $job
) {
    $user = $request->user();

    if ($job->user_id !== $user->id) {

        return response()->json([
            'success' => false,
            'message' => 'Unauthorized.',
        ], 403);
    }

    DB::transaction(function () use ($job) {


        $job->applications()->update([
            'status' => 'shortlisted',
            'reviewed_at' => now(),
        ]);


        $job->delete();
    });

    return response()->json([
        'success' => true,
        'message' => 'Job deleted successfully.',
    ]);
}



public function show($id)
    {
   
    $job = JobPost::with([
        'category',
        'user.jobProfile'
    ])
    ->withCount('applications')
    ->where('id', $id)
    ->where('status', 'accepted')
    ->whereDate('expire_date', '>=', now())
    ->firstOrFail();

    $job->increment('views');

    $job->refresh();


    $relatedJobs = JobPost::with([
        'category',
        'user.jobProfile'
    ])
    ->withCount('applications')
    ->where('status', 'accepted')
    ->whereDate('expire_date', '>=', now())
    ->where('job_category_id', $job->job_category_id)
    ->where('id', '!=', $job->id)
    ->latest()
    ->take(6)
    ->get();

    $previousJob = JobPost::with([
        'category',
        'user.jobProfile'
    ])
    ->where('status', 'accepted')
    ->whereDate('expire_date', '>=', now())
    ->where('id', '<', $job->id)
    ->orderByDesc('id')
    ->first();

    $nextJob = JobPost::with([
        'category',
        'user.jobProfile'
    ])
    ->where('status', 'accepted')
    ->whereDate('expire_date', '>=', now())
    ->where('id', '>', $job->id)
    ->orderBy('id')
    ->first();

    return response()->json([

        'success' => true,

        'job' => [

            'id' => $job->id,

            'title' => $job->title,

            'description' => $job->description,

            'about_us' => $job->about_us,

            'what_you_do' => $job->what_you_do,

            'location' => $job->location,

            'job_type' => $job->job_type,

            'currency' => $job->currency,

            'payment' => $job->payment,

            'payment_required' => (bool) $job->payment_required,

            'employee_needed' => $job->employee_needed,

            'additional_compensation' =>
                $job->additional_compensation,

            'enable_qualification' =>
                (bool) $job->enable_qualification,

            'qualification' =>
                $job->qualification,


            'enable_experience' =>
                (bool) $job->enable_experience,

            'experience' =>
                $job->experience,


            'enable_year_experience' =>
                (bool) $job->enable_year_experience,

            'year_experience' =>
                $job->year_experience,

            'status' => $job->status,

            'approved_at' => $job->approved_at,

            'expire_date' => $job->expire_date,

            'created_at' => $job->created_at,

            'updated_at' => $job->updated_at,

            'views' => $job->views,
            'application_count' =>
                $job->applications_count,

            'category' => $job->category,

            'user' => [

                'id' => $job->user->id,

                'first_name' =>
                    $job->user->first_name,

                'last_name' =>
                    $job->user->last_name,

                'email' =>
                    $job->user->email,

                'job_profile' =>
                    $job->user->jobProfile

            ]

        ],

        'related_jobs' => $relatedJobs,

        'previous_job' => $previousJob,

        'next_job' => $nextJob

    ]);
}




public function apply(Request $request, $jobId)
{
    $user = auth()->user();

    $job = JobPost::where('id', $jobId)
        ->where('status', 'accepted')
        ->whereDate('expire_date', '>=', now())
        ->firstOrFail();

    if ($job->user_id === $user->id) {

        return response()->json([
            'message' => 'You cannot apply for your own job.'
        ], 403);

    }

    $alreadyApplied = JobApplication::where(
        'job_post_id',
        $job->id
    )
    ->where(
        'user_id',
        $user->id
    )
    ->exists();


    if ($alreadyApplied) {

        return response()->json([
            'message' => 'You have already applied for this job.'
        ], 422);

    }

    $rules = [

        'cv' => [
            'nullable',
            'file',
            'mimes:pdf,doc,docx',
            'max:5120'
        ],

        'additional_text' => [
            'nullable',
            'string',
            'max:5000'
        ],

    ];

    if ($job->enable_qualification) {

        $rules['qualification'] = [
            'nullable',
            'string',
            'max:255'
        ];

    } else {

        $rules['qualification'] = [
            'nullable',
            'string',
            'max:255'
        ];

    }

    if ($job->enable_experience) {

        $rules['experience'] = [
            'required',
            'string',
            'max:2000'
        ];

    } else {

        $rules['experience'] = [
            'nullable',
            'string',
            'max:2000'
        ];

    }

    if ($job->enable_year_experience) {

        $rules['year_experience'] = [
            'required',
            'integer',
            'min:0',
            'max:100'
        ];

    } else {

        $rules['year_experience'] = [
            'nullable',
            'integer',
            'min:0',
            'max:100'
        ];

    }



    if ($job->payment_required) {

        $rules['payment'] = [
            'required',
            'numeric',
            'min:0'
        ];

    } else {

        $rules['payment'] = [
            'nullable',
            'numeric',
            'min:0'
        ];

    }

    $validated = $request->validate($rules);

   
    $cvPath = null;

    if ($request->hasFile('cv')) {

        $cvPath = $request
            ->file('cv')
            ->store(
                'job_applications/cv',
                'public'
            );

    }

    $application = JobApplication::create([

        'job_post_id' => $job->id,

        'user_id' => $user->id,

        'cv' => $cvPath,

        'additional_text' =>
            $validated['additional_text'] ?? null,

        'qualification' =>
            $validated['qualification'] ?? null,

        'experience' =>
            $validated['experience'] ?? null,

        'year_experience' =>
            $validated['year_experience'] ?? null,

        'payment' =>
            $validated['payment'] ?? null,

        'status' => 'pending',

    ]);



    return response()->json([

        'success' => true,

        'message' =>
            'Your application has been submitted successfully.',

        'application' => $application

    ], 201);
}


 public function store(Request $request)
{
    // Handle "Other" category first
    if ($request->job_category_id === "other") {

        $request->validate([
            'new_category' => 'required|string|max:255',
        ]);

        $slug = Str::slug($request->new_category);

        $category = JobCategory::firstOrCreate(
            ['slug' => $slug],
            [
                'name' => trim($request->new_category),
                'slug' => $slug,
                'description' => null,
                'icon' => null,
                'sort_order' => 999,
                'is_active' => true,
            ]
        );

        // Replace "other" with the actual category ID
        $request->merge([
            'job_category_id' => $category->id,
        ]);
        }

        $request->merge([

        'enable_qualification' => filter_var(
            $request->enable_qualification,
            FILTER_VALIDATE_BOOLEAN
        ),

        'enable_experience' => filter_var(
            $request->enable_experience,
            FILTER_VALIDATE_BOOLEAN
        ),

        'enable_year_experience' => filter_var(
            $request->enable_year_experience,
            FILTER_VALIDATE_BOOLEAN
        ),

        'payment_required' => filter_var(
            $request->payment_required,
            FILTER_VALIDATE_BOOLEAN
        ),

    ]);
    // Validate request
    $validated = $request->validate([
        'job_category_id' => 'required|exists:job_categories,id',
        'title' => 'required|string|max:255',
        'description' => 'required|string',
        'about_us' => 'required|string',
        'what_you_do' => 'required|string',
        'location' => 'nullable|string',
        'job_type' => 'required|in:remote,on-site,part-time',
        'currency' => 'required|string|max:10',
        'payment' => 'required|numeric',
        'employee_needed' => 'required|integer|min:1',
        'additional_compensation' => 'nullable|string',
        'enable_qualification' => 'nullable|boolean',
        'qualification' => 'nullable|string',
        'enable_experience' => 'nullable|boolean',
        'experience' => 'nullable|string',
        'enable_year_experience' => 'nullable|boolean',
        'year_experience' => 'nullable|integer|min:0',
        'payment_required' => 'nullable|boolean',
        'expire_date' => 'required|date|after:today',
    ]);

   $job = JobPost::create([
    'user_id' => auth()->id(),
        ...$validated,
        'status' => 'pending',
    ]);

    $job->load([
        'user',
        'category'
    ]);

    Mail::to('odukoyasheriff@gmail.com')
        ->send(new JobPostPendingApprovalMail($job));

    return response()->json([
        'success' => true,
        'message' => 'Job created successfully. Waiting for admin approval.',
        'job' => $job->load('category'),
    ], 201);
}



public function pendingJobs(Request $request)
{
    $jobs = JobPost::with([

        'user:id,first_name,last_name,email',

        'category'

    ])
    ->where('status', 'pending')
    ->latest()
    ->paginate(
        $request->per_page ?? 10
    );

    return response()->json([

        'success' => true,

        'jobs' => $jobs

    ]);
}




    public function approve($id)
    {
        $job = JobPost::with([
            'user',
            'category'
        ])->findOrFail($id);


        if ($job->status !== 'pending') {

            return response()->json([

                'message' => 'This job has already been processed.'

            ],422);

        }


        $job->update([

            'status' => 'accepted',

            'approved_by' => Auth::id(),

            'approved_at' => now(),

        ]);


        // Send approval email
        Mail::to($job->user->email)
            ->send(new JobApprovedMail($job->fresh([
                'user',
                'category'
            ])));



        return response()->json([

            'success' => true,

            'message' => 'Job approved successfully.',

            'job' => $job->fresh([
                'user',
                'category'
            ])

        ]);
    }





    public function decline(Request $request, $id)
    {

        $job = JobPost::with([
            'user',
            'category'
        ])->findOrFail($id);



        if ($job->status !== 'pending') {

            return response()->json([

                'message' => 'This job has already been processed.'

            ],422);

        }



        $job->update([

            'status' => 'declined',

            'approved_by' => Auth::id(),

            'approved_at' => now(),

        ]);



        // Send decline email
        Mail::to($job->user->email)
            ->send(new JobDeclinedMail($job->fresh([
                'user',
                'category'
            ])));



        return response()->json([

            'success' => true,

            'message' => 'Job declined successfully.',

            'job' => $job->fresh([
                'user',
                'category'
            ])

        ]);
    }


    /**
     * Delete Job
     */
    public function destroy($id)
    {


        $job = JobPost::findOrFail($id);



        // Owner or admin only

        if(
            Auth::id() !== $job->user_id
            &&
            !Auth::user()->is_admin
        ){

            return response()->json([

                'message'=>'Unauthorized'

            ],403);

        }



        DB::transaction(function() use($job){

            $job->applications()->delete();

            $job->views()->delete();

            $job->skills()->detach();

            $job->delete();

        });



        return response()->json([

            'message'=>'Job deleted successfully'

        ]);

    }


    public function skill()
{
    return response()->json([

        'skills'=>JobSkill::where(
            'is_active',
            true
        )->orderBy('name')->get()

    ]);
}

}