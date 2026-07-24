<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Job;
use Carbon\Carbon;

class JobController extends Controller

{
public function store(Request $request)
{

    $user = auth()->user();


    if(!$user->jobProfile ||
        $user->jobProfile->type != "creator")
    {

        return response()->json([

            "message"=>"Only Job Creators can post jobs."

        ],403);

    }



    $request->validate([

        "title"=>"required|string|max:255",

        "job_category_id"=>"required|exists:job_categories,id",

        "job_type"=>"required|in:remote,onsite",

        "location"=>"nullable|string|max:255",

        "payment"=>"required|numeric|min:1",

        "currency"=>"required|string",

        "expire_date"=>"required|date|after:today",

        "objective"=>"required",

        "description"=>"required"

    ]);


    $job = Job::create([

        'user_id'=>$user->id,

        'title'=>$request->title,

        'payment'=>$request->payment,

        'currency'=>$request->currency,

        'expire_date'=>$request->expire_date,

        'objective'=>$request->objective,

        'description'=>$request->description,

        'location'=>$request->location,

        'job_type'=>$request->job_type,

        'job_category_id'=>$request->job_category_id,

    ]);


    return response()->json([

        "message"=>"Job created successfully.",

        "job"=>$job

    ]);

}



    public function index()
{

    return Job::with([

        'creator.jobProfile',
        'category'

    ])
    ->where('active',true)
    ->whereDate(
        'expire_date',
        '>=',
        now()
    )
    ->latest()
    ->get();

}


public function update(Request $request,$id)
{

    $job = Job::findOrFail($id);


    if($job->user_id != auth()->id())
    {

        return response()->json([

            "message"=>"Unauthorized."

        ],403);

    }



    $request->validate([

        "title"=>"required|string|max:255",

        "job_category_id"=>"required|exists:job_categories,id",

        "job_type"=>"required|in:remote,onsite",

        "location"=>"nullable|string|max:255",

        "payment"=>"required|numeric|min:1",

        "currency"=>"required|string",

        "expire_date"=>"required|date",

        "objective"=>"required",

        "description"=>"required"

    ]);


    $job->update([

        'title'=>$request->title,

        'payment'=>$request->payment,

        'currency'=>$request->currency,

        'expire_date'=>$request->expire_date,

        'objective'=>$request->objective,

        'description'=>$request->description,

        'location'=>$request->location,

        'job_type'=>$request->job_type,

        'job_category_id'=>$request->job_category_id,

    ]);


    return response()->json([

        "message"=>"Job updated successfully.",

        "job"=>$job

    ]);


}

public function show($id)
{

    return Job::with([

        "creator.jobProfile",
        "category"

    ])->findOrFail($id);


}



public function destroy($id)
{

    $job = Job::findOrFail($id);


    if($job->user_id != auth()->id())
    {

        return response()->json([

            "message"=>"Unauthorized."

        ],403);

    }


    $job->delete();


    return response()->json([

        "message"=>"Deleted successfully."

    ]);

}

}

