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


class JobPostController extends Controller
{

public function index(Request $request)
{
    $query = JobPost::with([
        'category',
        'user:id,name,email'
    ])
    ->where('status', 'accepted')
    ->whereDate('expire_date', '>=', now());


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
            '%'.$request->location.'%'
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

    }

    $jobs = $query->paginate(12);

    return response()->json([

        'success' => true,

        'jobs' => $jobs

    ]);
}


public function show($id)
{
    $job = JobPost::with([

        'category',

        'user:id,name,email',

        'applications.user:id,name,email'

    ])
    ->where('status', 'accepted')
    ->findOrFail($id);

    $job->increment('views');

    $job->refresh();

    return response()->json([

        'success' => true,

        'job' => $job

    ]);
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
        'location' => 'required|string',
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

        'user:id,name,email',

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


    use Illuminate\Support\Facades\Auth;

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