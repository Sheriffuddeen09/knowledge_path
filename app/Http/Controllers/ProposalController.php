<?php

namespace App\Http\Controllers;
use App\Models\Proposal;    
use Illuminate\Http\Request;
use Carbon\Carbon;

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
        'expires_in' => 'required|in:20_minutes,7_days,14_days,30_days,60_days',
    ]);

            switch ($request->expires_in) {

            case '20_minutes':
                $expiresAt = now()->addMinutes(20);
                break;

            case '7_days':
                $expiresAt = now()->addDays(7);
                break;

            case '14_days':
                $expiresAt = now()->addDays(14);
                break;

            case '30_days':
                $expiresAt = now()->addDays(30);
                break;

            case '60_days':
            $expiresAt = now()->addDays(60);
            break;
        }

        $validated['student_id'] = auth()->id();
        $validated['expires_at'] = $expiresAt;
        //  'is_read' => false,
        $proposal = Proposal::create($validated);
        
            return response()->json([
            'message' => 'Proposal created successfully.',
            'proposal' => $proposal,
        ], 201);
        }



private function deleteExpiredProposals()
{
    Proposal::whereNotNull('expires_at')
        ->where('expires_at', '<=', now())
        ->delete();
}

   public function index()
{
    $this->deleteExpiredProposals();

    $teacher = auth()->user();

    if ($teacher->role !== 'admin') {
        return response()->json([
            'message' => 'Only teachers can view proposals.'
        ], 403);
    }

    $proposals = Proposal::with([
        'student:id,first_name,last_name,role,gender,address,location'
    ])
    ->where('student_deleted', false) // Hide deleted proposals
    ->whereDoesntHave('requests', function ($query) use ($teacher) {
        $query->where('teacher_id', $teacher->id)
              ->whereIn('status', [
                  'pending',
                  'accepted',
                  'declined'
              ]);
    })
    ->where(function ($query) {
        $query->whereNull('expires_at')
              ->orWhere('expires_at', '>', now());
    })
    ->latest()
    ->get();

    return response()->json($proposals);
}


public function myProposals()
{
    $student = auth()->user();

    $proposals = Proposal::where('student_id', $student->id)
        ->where('student_deleted', false)
        ->latest()
        ->get();

    return response()->json($proposals);
}



public function edit($id)
{
    $proposal = Proposal::where(
        'student_id',
        auth()->id()
    )->findOrFail($id);

    return response()->json($proposal);
}


public function update(Request $request,$id)
{
    $proposal = Proposal::where(
        'student_id',
        auth()->id()
    )->findOrFail($id);

    if($proposal->expires_at <= now()){

        return response()->json([
            'message'=>'Proposal has expired.'
        ],422);

    }

    $validated = $request->validate([
        'title'=>'required|string|max:255',
        'subject'=>'nullable|string|max:255',
        'price'=>'required|numeric',
        'currency'=>'required',
        'teacher_type'=>'required',
        'teaching_mode'=>'required',
        'preferred_location'=>'nullable',
        'qualification'=>'nullable',
        'teaching_hours'=>'required',
        'from_time'=>'required',
        'to_time'=>'required',
        'description'=>'required',
        'expires_in'=>'required|in:20_minutes,7_days,14_days,30_days,60_days',

    ]);

    switch ($request->expires_in) {
        case '20_minutes':
            $expiresAt = now()->addMinutes(20);
            break;
        case '7_days':
            $expiresAt = now()->addDays(7);
            break;
        case '14_days':
            $expiresAt = now()->addDays(14);
            break;
        case '30_days':
            $expiresAt = now()->addDays(30);
            break;

        case '60_days':
            $expiresAt = now()->addDays(60);
            break;
    }

    $validated['expires_at'] = $expiresAt;
    $proposal->update($validated);

    return response()->json([

        'message'=>'Proposal updated.',

        'proposal'=>$proposal

    ]);
}

public function destroy($id)
{
    $student = auth()->user();

    $proposal = Proposal::where(
        'student_id',
        $student->id
    )->findOrFail($id);

    // Cannot delete expired proposal
    if (
        $proposal->expires_at &&
        now()->greaterThanOrEqualTo($proposal->expires_at)
    ) {
        return response()->json([
            'message' => 'Proposal has already expired.'
        ],422);
    }

    $proposal->update([
        'student_deleted' => true
    ]);

    return response()->json([
        'message' => 'Proposal deleted successfully.'
    ]);
}

public function proposalNotification()
{
    $teacher = auth()->user();

    if ($teacher->role !== 'admin') {
        return response()->json([
            'pending_proposals' => 0
        ]);
    }

    $count = Proposal::where('student_deleted', false)
        ->where('is_read', false)
        ->where(function ($query) {
            $query->whereNull('expires_at')
                  ->orWhere('expires_at', '>', now());
        })
        ->whereDoesntHave('requests', function ($query) use ($teacher) {
            $query->where('teacher_id', $teacher->id)
                  ->whereIn('status', [
                      'pending',
                      'accepted',
                      'declined'
                  ]);
        })
        ->count();

    return response()->json([
        'pending_proposals' => $count
    ]);
}

public function markProposalsAsRead()
{
    $teacher = auth()->user();

    Proposal::where('student_deleted', false)
        ->where('is_read', false)
        ->where(function ($query) {
            $query->whereNull('expires_at')
                  ->orWhere('expires_at', '>', now());
        })
        ->whereDoesntHave('requests', function ($query) use ($teacher) {
            $query->where('teacher_id', $teacher->id)
                  ->whereIn('status', [
                      'pending',
                      'accepted',
                      'declined'
                  ]);
        })
        ->update([
            'is_read' => true
        ]);

    return response()->json([
        'success' => true
    ]);
}
}
