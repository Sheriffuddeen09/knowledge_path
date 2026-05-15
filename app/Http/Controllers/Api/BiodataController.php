<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Biodata;
use Illuminate\Http\Request;

class BiodataController extends Controller
{
    // DASHBOARD (LOGGED IN USER)
    public function me(Request $request)
    {
        $bio = Biodata::with(['educations', 'careers'])
            ->where('user_id', $request->user()->id)
            ->first();

        return response()->json($bio);
    }

    // PROFILE BY ID (PUBLIC VIEW)
    public function show($id)
    {
        $bio = Biodata::with(['educations', 'careers'])
            ->where('user_id', $id)
            ->first();

        return response()->json($bio);
    }

    // CREATE OR UPDATE MAIN BIO
    public function store(Request $request)
    {
        $bio = Biodata::updateOrCreate(
            ['user_id' => $request->user()->id],
            $request->only([
                'marital_status',
                'address',
                'state',
                'bio'
            ])
        );

        return response()->json($bio);
    }

    public function update(Request $request)
    {
        return $this->store($request);
    }

    // EDUCATION
    public function addEducation(Request $request)
    {
        $bio = Biodata::where(
            'user_id',
            $request->user()->id
        )->first();

        if (!$bio) {
            return response()->json([
                'message' => 'Create biodata first'
            ], 404);
        }

        $bio->educations()->create([
            'school' => $request->school,
            'course' => $request->course,
            'year' => $request->year,
        ]);

        return response()->json([
            'message' => 'Education added'
        ]);
    }

    // CAREER
    public function addCareer(Request $request)
    {
        $bio = Biodata::where(
            'user_id',
            $request->user()->id
        )->first();

        if (!$bio) {
            return response()->json([
                'message' => 'Create biodata first'
            ], 404);
        }

        $bio->careers()->create([
            'company' => $request->company,
            'role' => $request->role,
            'duration' => $request->duration,
        ]);

        return response()->json([
            'message' => 'Career added'
        ]);
    }
}