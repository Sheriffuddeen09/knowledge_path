<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreJobApplicationRequest;
use App\Models\JobApplication;
use App\Models\JobPost;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Mail\JobApplicationAcceptedMail;
use App\Models\JobInterview;
use App\Mail\JobApplicationDeclinedMail;
use Illuminate\Support\Facades\Mail;
use App\Mail\JobWithdrawnMail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Carbon\Carbon;


class JobApplicationController extends Controller
{
    public function store(
        StoreJobApplicationRequest $request,
        JobPost $job
    ) {

        $user = $request->user();

 
        if ($job->status !== 'accepted') {

            return response()->json([
                'success' => false,
                'message' => 'This job is not currently available.'
            ], 422);

        }

        if (
            $job->expire_date &&
            $job->expire_date->isPast()
        ) {

            return response()->json([
                'success' => false,
                'message' => 'This job application has expired.'
            ], 422);

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
                'success' => false,
                'message' =>
                    'You have already applied for this job.'
            ], 422);

        }

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
                $request->input('additional_text'),

            'qualification' =>
                $job->enable_qualification
                    ? $request->input('qualification')
                    : null,

            'experience' =>
                $job->enable_experience
                    ? $request->input('experience')
                    : null,

            'year_experience' =>
                $job->enable_year_experience
                    ? $request->input('year_experience')
                    : null,

            'payment' =>
                $job->payment_required
                    ? $request->input('payment')
                    : null,

            'currency' => $job->currency,

            'status' => 'pending',

            'read_by_poster_at' => null,

        ]);


        return response()->json([

            'success' => true,

            'message' =>
                'Your application has been submitted successfully.',

            'application' => [

                'id' => $application->id,

                'job_post_id' =>
                    $application->job_post_id,

                'status' =>
                    $application->status,

                'created_at' =>
                    $application->created_at,

            ],

            'application_count' =>
                JobApplication::where(
                    'job_post_id',
                    $job->id
                )->count(),

        ], 201);
    }

    public function status(
        Request $request,
        JobPost $job
    ) {

        $application = JobApplication::where(
            'job_post_id',
            $job->id
        )
        ->where(
            'user_id',
            $request->user()->id
        )
        ->first();

        return response()->json([

            'success' => true,

            'applied' => $application !== null,

            'application' => $application,

        ]);
    }


    public function myApplications(Request $request)
{
    $user = $request->user();

    $applications = JobApplication::with([
        'jobPost.category',
        'jobPost.user.jobProfile',
    ])
    ->where('user_id', $user->id)
    ->whereNull('removed_by_applicant_at')
    ->latest()
    ->paginate(10);


    $applications->getCollection()->transform(
        function ($application) {

            $job = $application->jobPost;

            $status = $application->status;

        if (!$job) {

                return [

                    'id' => $application->id,

                    'job_post_id' =>
                        $application->job_post_id,

                    'status' => 'shortlisted',

                    'status_label' =>
                        'Job Deleted',

                    'created_at' =>
                        $application->created_at,

                    'job' => null,

                ];

            }
            if ($job->deleted_at) {

                return [

                    'id' => $application->id,

                    'job_post_id' =>
                        $application->job_post_id,

                    'status' => 'shortlisted',

                    'status_label' =>
                        'Job Deleted by Employer',

                    'created_at' =>
                        $application->created_at,

                    'updated_at' =>
                        $application->updated_at,

                    'job' => [

                        'id' => $job->id,

                        'title' => $job->title,

                        'description' => $job->description,

                        'deleted_at' => $job->deleted_at,

                    ],

                ];

            }

            if (
                $job->expire_date &&
                $job->expire_date->isPast()
            ) {

                $status = 'expired';

            }


            return [

                'id' =>
                    $application->id,

                'job_post_id' =>
                    $application->job_post_id,

                'status' =>
                    $status,

                'status_label' =>
                    match ($status) {

                        'pending' =>
                            'Application Pending',

                        'accepted' =>
                            'Application Accepted',

                        'rejected' =>
                            'Application Rejected',

                        'reviewed' =>
                            'Application Withdrawn',
                             
                        'shortlisted' =>
                            'Job Deleted By Employer',

                        'expired' =>
                            'Job Expired',

                        default =>
                            ucfirst($status),

                    },

                'created_at' =>
                    $application->created_at,

                'updated_at' =>
                    $application->updated_at,

                'job' => [

                    'id' =>
                        $job->id,

                    'title' =>
                        $job->title,

                    'description' =>
                        $job->description,

                    'location' =>
                        $job->location,

                    'job_type' =>
                        $job->job_type,

                    'currency' =>
                        $job->currency,

                    'payment' =>
                        $job->payment,

                    'expire_date' =>
                        $job->expire_date,

                    'approved_at' =>
                        $job->approved_at,

                    'views' =>
                        $job->views,

                    'application_count' =>
                        $job->applications()->count(),

                    'category' =>
                        $job->category,

                    'company' => [

                        'user_id' =>
                            $job->user?->id,

                        'name' =>
                            $job->user?->jobProfile?->company_name
                            ??
                            trim(
                                ($job->user?->first_name ?? '') .
                                ' ' .
                                ($job->user?->last_name ?? '')
                            ),

                        'logo' =>
                            $job->user?->jobProfile?->company_logo,

                        'company_type' =>
                            $job->user?->jobProfile?->company_type,

                        'location' =>
                            $job->user?->jobProfile?->company_location,

                    ],

                ],

            ];

        }
    );


    return response()->json([

        'success' => true,

        'applications' => $applications,

    ]);
}


    public function index(Request $request)
{
    $user = $request->user();

    $unreadCount = JobApplication::whereHas(
        'jobPost',
        function ($query) use ($user) {

            $query->where(
                'user_id',
                $user->id
            );

        }
    )
    ->whereNull('removed_by_poster_at')
    ->whereNull('read_by_poster_at')
    ->count();

    $applications = JobApplication::with([
        'user.jobProfile',
        'jobPost.category',
        'interview',
    ])
    ->whereHas(
        'jobPost',
        function ($query) use ($user) {

            $query->where(
                'user_id',
                $user->id
            );

        }
    )
    ->whereNull('removed_by_poster_at')
    ->latest()
    ->paginate(10);


    return response()->json([

        'success' => true,

        'applications' => $applications,

        'unread_count' => $unreadCount,

    ]);
}



public function unreadCount(Request $request)
{
    $user = $request->user();

    $count = JobApplication::whereNull(
        'read_by_poster_at'
    )
    ->whereHas('jobPost', function ($query) use ($user) {

        $query->where(
            'user_id',
            $user->id
        );

    })
    ->whereNull('removed_by_poster_at')
    ->count();

    return response()->json([
        'success' => true,
        'count' => $count,
    ]);
}

    public function markAsRead(Request $request)
{
    $user = $request->user();

    JobApplication::whereNull(
        'read_by_poster_at'
    )
    ->whereHas('jobPost', function ($query) use ($user) {

        $query->where(
            'user_id',
            $user->id
        );

    })
    ->whereNull('removed_by_poster_at')
    ->update([
        'read_by_poster_at' => now(),
    ]);

    return response()->json([
        'success' => true,
        'message' => 'Applications marked as read.',
    ]);
}
public function accept(
    Request $request,
    JobApplication $application
) {
    $user = $request->user();

    $application->load([
        'jobPost',
        'user',
        'user.jobProfile',
    ]);
        // call_link
            $validated = $request->validate([

            'interview_date' => [
                'required',
                'date',
                'after_or_equal:today',
            ],

            'interview_time' => [
                'required',
                'date_format:H:i',
            ],

            'notes' => [
                'nullable',
                'string',
                'max:5000',
            ],

            'call_link' => [
                'required',
                'url',
                'max:2000',
            ],

        ]);

    if ($application->status === 'accepted') {

        return response()->json([
            'success' => false,
            'message' =>
                'This application has already been accepted.',
        ], 422);

    }

    $token = Str::random(64);

    $frontendUrl = config(
        'app.frontend_url',
        'http://localhost:3000'
    );

    $interviewPageLink =
        rtrim($frontendUrl, '/') .
        '/job-interview/' .
        $token;

    $application->update([

        'status' => 'accepted',

        'reviewed_at' => now(),

    ]);


        $interview = JobInterview::create([

        'job_application_id' => $application->id,

        'interview_token' => $token,

        'interview_date' =>
            $validated['interview_date'],

        'interview_time' =>
            $validated['interview_time'],

        'meeting_link' =>
            rtrim($frontendUrl, '/') .
            '/job-interview/' .
            $token,

        'call_link' =>
            $validated['call_link'],

        'status' => 'scheduled',

        'notes' =>
            $validated['notes'] ?? null,

    ]);



    Mail::to(
        $application->user->email
    )->send(
        new JobApplicationAcceptedMail(
            $application,
            $interview
        )
    );


    return response()->json([

        'success' => true,

        'message' =>
            'Application accepted and interview scheduled successfully.',

        'application' =>
            $application->load([
                'user.jobProfile',
                'jobPost.category',
                'interview',
            ]),

    ]);
}



public function decline(
    Request $request,
    JobApplication $application
) {
    $user = $request->user();

    

    $application->load([
        'user',
        'jobPost.user.jobProfile',
    ]);

    
    
    if ($application->status !== 'pending') {

        return response()->json([
            'success' => false,
            'message' => 'This application has already been processed.',
        ], 422);

    }

    

    $application->update([

        'status' => 'rejected',

        'reviewed_at' => now(),

    ]);

    

    $application->refresh();

    $application->load([
        'user',
        'jobPost.user.jobProfile',
    ]);

   

    if ($application->user?->email) {

        Mail::to($application->user->email)
            ->send(
                new JobApplicationDeclinedMail(
                    $application
                )
            );

    }


    return response()->json([

        'success' => true,

        'message' =>
            'Application declined successfully. The applicant has been notified by email.',

        'application' => $application,

    ]);
}


  
public function withdraw(
    Request $request,
    JobApplication $application
) {

    $user = $request->user();

   

    $application->load([
        'user',
        'jobPost.user.jobProfile',
    ]);

    
    if (
        !in_array(
            $application->status,
            ['pending', 'accepted']
        )
    ) {

        return response()->json([
            'success' => false,
            'message' =>
                'This application has already been processed.',
        ], 422);

    }

    

    $application->update([

        'status' => 'reviewed',

        'reviewed_at' => now(),

    ]);


    $application->refresh();

    $application->load([
        'user',
        'jobPost.user.jobProfile',
    ]);


    if ($application->user?->email) {

        Mail::to($application->user->email)
            ->send(
                new JobWithdrawnMail(
                    $application
                )
            );

    }

    
    return response()->json([

        'success' => true,

        'message' =>
            'Application Withdraw successfully. The applicant has been notified by email.',

        'application' => $application,

    ]);
}


public function interview($token)
{
    $interview = JobInterview::with([
        'application.user.jobProfile',
        'application.jobPost.category',
        'application.jobPost.user.jobProfile',
    ])
    ->where('interview_token', $token)
    ->firstOrFail();

    $interviewDate = Carbon::parse(
        $interview->interview_date
    )->format('Y-m-d');

    $interviewTime = Carbon::parse(
        $interview->interview_time
    )->format('H:i:s');


    $interviewDateTime = Carbon::parse(
        $interviewDate . ' ' . $interviewTime
    );

    $isExpired = now()->greaterThan(
        $interviewDateTime
    );


    return response()->json([

        'success' => true,

        'interview' => [

            'id' =>
                $interview->id,

            'interview_date' =>
                $interview->interview_date,

            'interview_time' =>
                $interview->interview_time,

            'meeting_link' =>
                $interview->meeting_link,

            'call_link' =>
                $interview->call_link,

            'interview_token' =>
                $interview->interview_token,

            'status' =>
                $interview->status,

            'notes' =>
                $interview->notes,

            'is_expired' =>
                $isExpired,

            'interview_datetime' =>
                $interviewDateTime->toIso8601String(),

            'application' =>
                $interview->application,

        ],

    ]);
}

    public function removeByPoster(
    Request $request,
    JobApplication $application
) {
    $user = $request->user();

    $application->load('jobPost');


    if (
        !$application->jobPost ||
        $application->jobPost->user_id !== $user->id
    ) {

        return response()->json([
            'success' => false,
            'message' => 'Unauthorized.',
        ], 403);

    }


    if ($application->removed_by_poster_at) {

        return response()->json([
            'success' => false,
            'message' => 'Application has already been removed.',
        ], 422);

    }


    $application->update([
        'removed_by_poster_at' => now(),
    ]);


    return response()->json([

        'success' => true,

        'message' =>
            'Application removed successfully.',

    ]);
}


public function removeByApplicant(
    Request $request,
    JobApplication $application
) {
    $user = $request->user();


    if (
        $application->user_id !== $user->id
    ) {

        return response()->json([
            'success' => false,
            'message' => 'Unauthorized.',
        ], 403);

    }


    if ($application->removed_by_applicant_at) {

        return response()->json([
            'success' => false,
            'message' => 'Application has already been removed.',
        ], 422);

    }


    $application->update([
        'removed_by_applicant_at' => now(),
    ]);


    return response()->json([

        'success' => true,

        'message' =>
            'Application removed from your applications.',

    ]);
}


}