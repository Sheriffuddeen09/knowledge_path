<?php

namespace App\Http\Controllers;

use App\Models\JobPost;
use App\Models\JobApplication;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class JobApplicationController extends Controller
{


    /**
     * Job Finder Apply For Job
     */
    public function apply(Request $request,$jobId)
    {


        $request->validate([

            'cover_letter'=>'nullable|string',

            'resume'=>'nullable|file|mimes:pdf,doc,docx|max:5120',

            'currency'=>'nullable|string',

            'expected_salary'=>'nullable|numeric'

        ]);



        $job = JobPost::where('status','accepted')
            ->findOrFail($jobId);



        // Check expiry

        if(
            $job->expire_date < now()->toDateString()
        ){

            return response()->json([

                'message'=>'This job has expired'

            ],400);

        }



        // Prevent duplicate apply

        $exists = JobApplication::where([

            'job_post_id'=>$job->id,

            'user_id'=>Auth::id()

        ])->exists();



        if($exists){

            return response()->json([

                'message'=>'You already applied for this job'

            ],400);

        }




        $resume = null;


        if($request->hasFile('resume')){


            $resume = $request
                ->file('resume')
                ->store('job-resumes','public');


        }




        $application = JobApplication::create([


            'job_post_id'=>$job->id,


            'user_id'=>Auth::id(),


            'job_owner_id'=>$job->user_id,


            'cover_letter'=>$request->cover_letter,


            'resume'=>$resume,


            'currency'=>$request->currency ?? $job->currency,


            'expected_salary'=>$request->expected_salary,


            'status'=>'pending'


        ]);




        // increase application count

        $job->increment('application_count');



        return response()->json([

            'message'=>'Application submitted successfully',

            'application'=>$application

        ],201);



    }







    /**
     * Job Finder Dashboard
     */
    public function myApplications()
    {


        $applications = JobApplication::with([

            'job.category',

            'job.user'

        ])

        ->where('user_id',Auth::id())

        ->latest()

        ->paginate(10);



        return response()->json($applications);

    }







    /**
     * Job Poster View Applicants
     */
    public function jobApplicants($jobId)
    {


        $job = JobPost::where('user_id',Auth::id())

            ->findOrFail($jobId);



        $applications = JobApplication::with([

            'applicant'

        ])

        ->where('job_post_id',$job->id)

        ->latest()

        ->paginate(10);



        return response()->json($applications);


    }







    /**
     * View Applicant Profile
     */
    public function showApplicant($id)
    {


        $application = JobApplication::with([

            'applicant.jobProfile'

        ])

        ->findOrFail($id);



        return response()->json($application);


    }







    /**
     * Accept Applicant
     */
    public function accept($id)
    {


        $application = JobApplication::whereHas('job',

            function($query){

                $query->where(
                    'user_id',
                    Auth::id()
                );

            }

        )

        ->findOrFail($id);




        $application->update([

            'status'=>'accepted',

            'reviewed_by'=>Auth::id(),

            'reviewed_at'=>now()

        ]);




        return response()->json([

            'message'=>'Applicant accepted successfully',

            'application'=>$application

        ]);


    }







    /**
     * Reject Applicant
     */
    public function reject(Request $request,$id)
    {


        $application = JobApplication::whereHas('job',

            function($query){

                $query->where(
                    'user_id',
                    Auth::id()
                );

            }

        )

        ->findOrFail($id);




        $application->update([


            'status'=>'rejected',


            'remark'=>$request->remark,


            'reviewed_by'=>Auth::id(),


            'reviewed_at'=>now()


        ]);




        return response()->json([

            'message'=>'Applicant rejected',

            'application'=>$application

        ]);

    }







    /**
     * Job Finder Withdraw Application
     */
    public function withdraw($id)
    {


        $application = JobApplication::where(

            'user_id',

            Auth::id()

        )

        ->findOrFail($id);



        $application->update([

            'status'=>'withdrawn'

        ]);



        return response()->json([

            'message'=>'Application withdrawn'

        ]);

    }




}