<?php

namespace App\Http\Controllers;
use App\Models\Proposal;    
use Illuminate\Http\Request;

class ProposalController extends Controller
{
    public function store(Request $request)
{
    $user = auth()->user();

    if ($user->role !== 'student') {
        return response()->json([
            'message' => 'Only students can create proposals.'
        ], 403);
    }

    $validated = $request->validate([
        'title' => 'required|string|max:255',
        'subject' => 'nullable|string|max:255',
        'price' => 'required|numeric|min:0',
        'currency' => 'required|string|max:10',
        'teacher_type' => 'required|in:male,female,any',
        'teaching_mode' => 'required|in:online,physical',
        'preferred_location' => 'nullable|string|max:255',
        'qualification' => 'nullable|string|max:255',
        'teaching_hours' => 'required|integer|min:1',
        'from_time' => 'required|date_format:H:i',
        'to_time' => 'required|date_format:H:i|after:from_time',
        'description' => 'required|string',
    ]);

    $validated['student_id'] = $user->id;

    $proposal = Proposal::create($validated);

    return response()->json([
    'message' => 'Proposal created successfully.',
    'proposal' => $proposal,
], 201);
}


    public function index()
    {
        $user = auth()->user();

        if ($user->role !== 'admin') {
            return response()->json([
                'message' => 'Only teachers can view proposals.'
            ], 403);
        }

        $proposals = Proposal::with([
            'student:id,first_name,last_name,role,gender,address,location'
        ])
        ->latest()
        ->get();

        return response()->json($proposals);
    }
}
