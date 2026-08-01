<?php

namespace App\Http\Controllers;

use App\Models\JobProfile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;


class JobProfileController extends Controller
{
    public function show(Request $request)
    {
        return $request->user()->jobProfile;
    }


    public function store(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([

            'type' => 'required|in:creator,finder',

            'company_name' => 'nullable|string|max:255',
            'company_logo'=>'nullable|image',
            'company_type' => 'nullable|in:individual,organisation',
            'organisation_size' => 'nullable|string|max:255',
            'company_location' => 'nullable|string|max:255',
            'company_address' => 'nullable|string|max:255',

            'full_name' => 'nullable|string|max:255',
            'cv' => 'nullable|file|mimes:pdf,doc,docx|max:5120',
            'qualifications' => 'nullable|string',
            'portfolio' => 'nullable|string',
            'skills' => 'nullable|array',
            'skills.*' => 'string|max:100',
            'certification' => 'nullable|string',
            'location' => 'nullable|string|max:255',
            'address' => 'nullable|string|max:255',

        ]);

        if ($request->filled('skills')) {

            $data['skills'] = $request->skills;

                    }

                    if ($request->filled('skills')) {

                $validated['skills'] = $request->skills;

            }

        if ($request->hasFile('company_logo')) {

            $data['company_logo'] = $request
                ->file('company_logo')
                ->store('company_logo', 'public');

        }
        if ($request->hasFile('cv')) {

            $data['cv'] = $request
                ->file('cv')
                ->store('cv', 'public');

        }


        $data['user_id'] = $user->id;


        $profile = JobProfile::updateOrCreate(

            [
                'user_id' => $user->id
            ],

            $data

        );


        return response()->json([

            'message' => 'Profile created successfully.',

            'profile' => $profile

        ], 201);
    }



    public function update(Request $request, $id)
{
    Log::info('========== UPDATE START ==========');

    Log::info('Profile ID', [
        'id' => $id,
    ]);

    Log::info('Authenticated User', [
        'user_id' => auth()->id(),
    ]);

    Log::info('Request Method', [
        'method' => $request->method(),
    ]);

    Log::info('All Request Data', [
        'data' => $request->all(),
    ]);

    Log::info('Files Received', [
        'has_cv' => $request->hasFile('cv'),
        'has_logo' => $request->hasFile('company_logo'),
    ]);


    $user = $request->user();


    $profile = JobProfile::where('id', $id)
        ->where('user_id', $user->id)
        ->firstOrFail();


    Log::info('Profile Found', [
        'profile_id' => $profile->id,
        'type' => $profile->type,
    ]);

    Log::info('CV Information',[
    'has_file'=>$request->hasFile('cv'),
    'cv'=>$request->file('cv'),
    'extension'=>$request->file('cv')
        ? $request->file('cv')->getClientOriginalExtension()
        : null,
    'mime'=>$request->file('cv')
        ? $request->file('cv')->getMimeType()
        : null,
    'name'=>$request->file('cv')
        ? $request->file('cv')->getClientOriginalName()
        : null,
]);

    $validated = $request->validate([

        'company_name' => 'nullable|string|max:255',
        'company_logo' => 'nullable|image',
        'company_type' => 'nullable|in:individual,organisation',
        'organisation_size' => 'nullable|string|max:255',
        'company_location' => 'nullable|string|max:255',
        'company_address' => 'nullable|string|max:255',

        'full_name' => 'nullable|string|max:255',
        'cv' => 'nullable|mimes:pdf,doc,docx|max:5120',
        'qualifications' => 'nullable|string',
        'portfolio' => 'nullable|string|max:500',
        'certification' => 'nullable|string',
        'skills' => 'nullable|array',
        'skills.*' => 'string|max:100',
        'location' => 'nullable|string|max:255',
        'address' => 'nullable|string|max:255',

    ]);


    Log::info('Validation Passed', [
        'validated' => $validated,
    ]);


    if ($request->hasFile('company_logo')) {

        Log::info('Uploading Company Logo');

        if (
            $profile->company_logo &&
            Storage::disk('public')->exists(
                $profile->company_logo
            )
        ) {

            Storage::disk('public')
                ->delete(
                    $profile->company_logo
                );

            Log::info(
                'Old Company Logo Deleted'
            );

        }

        $validated['company_logo'] = $request
            ->file('company_logo')
            ->store(
                'company_logo',
                'public'
            );

        Log::info('New Company Logo Uploaded', [
            'path' => $validated['company_logo'],
        ]);

    }


    if ($request->hasFile('cv')) {

        Log::info('Uploading CV');

        if (
            $profile->cv &&
            Storage::disk('public')->exists(
                $profile->cv
            )
        ) {

            Storage::disk('public')
                ->delete(
                    $profile->cv
                );

            Log::info(
                'Old CV Deleted'
            );

        }

        $validated['cv'] = $request
            ->file('cv')
            ->store(
                'cv',
                'public'
            );

        Log::info('New CV Uploaded', [
            'path' => $validated['cv'],
        ]);

    }


    if ($request->filled('skills')) {

        $validated['skills'] = json_encode(
            $request->skills
        );

        Log::info('Skills Updated', [
            'skills' => $validated['skills'],
        ]);

    }


    Log::info('Updating Profile', [
        'data' => $validated,
    ]);


    $profile->update(
        $validated
    );


    Log::info('Profile Updated Successfully', [
        'profile' => $profile->fresh(),
    ]);


    Log::info('========== UPDATE END ==========');


    return response()->json([

        'message' => 'Profile updated successfully.',

        'profile' => $profile->fresh(),

    ]);
}
}