<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreJobApplicationRequest;
use App\Models\JobApplication;
use App\Models\JobPost;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

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
}