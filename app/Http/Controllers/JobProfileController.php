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
            $profile = $request->user()
                ->jobProfile()
                ->with('category')
                ->first();

            if ($profile && $profile->skills) {
                $profile->skills = json_decode($profile->skills, true) ?? [];
            } else if ($profile) {
                $profile->skills = [];
            }

            return response()->json($profile);
        }


            public function store(Request $request)
            {
            $user = $request->user();

            $data = $request->validate([

                'type' => 'required|in:creator,finder',

                // Creator
                'company_name' => 'nullable|string|max:255',
                'company_logo' => 'nullable|image',
                'company_type' => 'nullable|in:individual,organisation',
                'organisation_size' => 'nullable|string|max:255',
                'company_location' => 'nullable|string|max:255',
                'company_address' => 'nullable|string|max:255',

                // Finder
                'full_name' => 'nullable|string|max:255',

                'job_category_id' => [
                    'required_if:type,finder',
                    'exists:job_categories,id',
                ],

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
                $data['skills'] = json_encode($request->skills);
            } else {
                $data['skills'] = null;
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
                    'user_id' => $user->id,
                ],
                $data
            );


            $profile->load('category');

            return response()->json([

                'message' => 'Profile created successfully.',

                'profile' => $profile,

            ], 201);
            }




            public function update(Request $request, $id)
            {
            Log::info('Job profile update request', [

                'method' => $request->method(),

                'content_type' =>
                    $request->header('Content-Type'),

                'has_company_logo' =>
                    $request->hasFile('company_logo'),

                'company_logo' =>
                    $request->file('company_logo'),

                'company_logo_mime' =>
                    $request->file('company_logo')
                        ?->getMimeType(),

                'company_logo_client_mime' =>
                    $request->file('company_logo')
                        ?->getClientMimeType(),

                'company_logo_name' =>
                    $request->file('company_logo')
                        ?->getClientOriginalName(),

                'company_logo_size' =>
                    $request->file('company_logo')
                        ?->getSize(),

                'all_request_data' =>
                    $request->except([
                        'company_logo',
                        'cv',
                    ]),
            ]);

            $user = $request->user();

            $profile = JobProfile::where('id', $id)
                ->where('user_id', $user->id)
                ->firstOrFail();

            $validated = $request->validate([

                // Creator
                'company_name' => 'nullable|string|max:255',
                'company_logo' => 'nullable|image',
                'company_type' => 'nullable|in:individual,organisation',
                'organisation_size' => 'nullable|string|max:255',
                'company_location' => 'nullable|string|max:255',
                'company_address' => 'nullable|string|max:255',

                // Finder
                'full_name' => 'nullable|string|max:255',

                'job_category_id' => [
                    'nullable',
                    'exists:job_categories,id',
                ],

                'cv' => 'nullable|file|mimes:pdf,doc,docx|max:5120',
                'qualifications' => 'nullable|string',
                'portfolio' => 'nullable|string|max:500',
                'certification' => 'nullable|string',
                'skills' => 'nullable|array',
                'skills.*' => 'string|max:100',
                'location' => 'nullable|string|max:255',
                'address' => 'nullable|string|max:255',
            ]);

            if ($request->hasFile('company_logo')) {

                if (
                    $profile->company_logo &&
                    Storage::disk('public')->exists(
                        $profile->company_logo
                    )
                ) {
                    Storage::disk('public')->delete(
                        $profile->company_logo
                    );
                }

                $validated['company_logo'] = $request
                    ->file('company_logo')
                    ->store(
                        'company_logo',
                        'public'
                    );
            }


            if ($request->hasFile('cv')) {

                if (
                    $profile->cv &&
                    Storage::disk('public')->exists(
                        $profile->cv
                    )
                ) {
                    Storage::disk('public')->delete(
                        $profile->cv
                    );
                }

                $validated['cv'] = $request
                    ->file('cv')
                    ->store(
                        'cv',
                        'public'
                    );
            }


            if ($request->filled('skills')) {

                $validated['skills'] = json_encode(
                    $request->skills
                );

            } else {

                $validated['skills'] = null;
            }


            $profile->update($validated);


            $profile->load('category');

            return response()->json([

                'message' => 'Profile updated successfully.',

                'profile' => $profile,

            ]);
            }
}