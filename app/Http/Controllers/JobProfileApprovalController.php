<?php

namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use App\Models\JobProfile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use App\Mail\JobProfileApproved;
use App\Mail\JobProfileDeclined; 


class JobProfileApprovalController extends Controller
{
 public function index()
 {
 return JobProfile::with('user')
 ->latest()
 ->get();
 }
 public function show($id)
 {
 return JobProfile::with('user')
 ->findOrFail($id);
 }


 public function approve($id)
 {
 $profile = JobProfile::findOrFail($id);
 $profile->update([
 'status' => 'approved',
 'decline_reason' => null
 ]);
Mail::to($profile->user->email)
 ->send(new JobProfileApproved($profile));

 return response()->json([
 'message' => 'Profile approved.'
 ]);

 }


 public function decline(Request $request, $id)
 {
 $request->validate([
 'reason' => 'required|string'
 ]);
 $profile = JobProfile::findOrFail($id);
 $profile->update([
 'status' => 'declined',
 'decline_reason' => $request->reason
 ]);
    
 Mail::to($profile->user->email)
 ->send(new JobProfileDeclined($profile));

 return response()->json([
 'message' => 'Profile declined.'
 ]);
 }

}