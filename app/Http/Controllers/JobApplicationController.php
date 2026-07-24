<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class JobApplicationController extends Controller
{
    public function apply(Request $request,$id)
{

    $user=auth()->user();


    $job=Job::findOrFail($id);


    if($job->expire_date < now())
    {
        return response()->json([

            'message'=>'Job expired.'

        ],422);
    }



    if($job->user_id == $user->id)
    {

        return response()->json([

            'message'=>'You cannot apply.'

        ],422);

    }



    $exists=JobApplication::where(
        'job_id',$id
    )
    ->where(
        'job_finder_id',
        $user->id
    )->exists();


    if($exists)
    {
        return response()->json([

            'message'=>'Already applied.'

        ],422);
    }



    JobApplication::create([

        'job_id'=>$id,
        'job_finder_id'=>$user->id,
        'message'=>$request->message

    ]);


    return response()->json([

        'message'=>'Application sent.'

    ]);

}  

public function applications($id)
{

    return JobApplication::with([

        'finder.jobProfile'

    ])
    ->where(
        'job_id',
        $id
    )
    ->latest()
    ->get();

}

public function accept($id)
{

    $application=
    JobApplication::findOrFail($id);


    $application->update([

        'status'=>'accepted'

    ]);


    return response()->json([

        'message'=>'Accepted.'

    ]);

}

public function reject($id)
{

    $application=
    JobApplication::findOrFail($id);


    $application->update([

        'status'=>'rejected'

    ]);


    return response()->json([

        'message'=>'Rejected.'

    ]);

}
}
