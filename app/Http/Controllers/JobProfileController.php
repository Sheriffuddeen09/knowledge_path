<?php

namespace App\Http\Controllers;

use App\Models\JobProfile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use App\Mail\JobProfileSubmitted;
use Illuminate\Support\Facades\Storage;

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
 'type'=>'required|in:creator,finder',
 'company_name'=>'nullable|string|max:255',
 'company_logo'=>'nullable|image',
 'company_type'=>'nullable|in:individual,organisation',
 'organisation_size'=>'nullable|string|max:255',
 'company_location'=>'nullable|string|max:255',
 'full_name'=>'nullable|string|max:255',
 'cv'=>'nullable|file|mimes:pdf,doc,docx',
 'qualifications'=>'nullable|string',
 'portfolio'=>'nullable|string',
 'skills'=>'nullable|string',
 'certification'=>'nullable|string'
 ]);
 if($request->hasFile('company_logo')){
 $data['company_logo']=$request
 ->file('company_logo')
 ->store('company_logo','public');
 }
 if($request->hasFile('cv')){
 $data['cv']=$request
 ->file('cv')
 ->store('cv','public');
 }
 $data['user_id']=$user->id;
 $profile=JobProfile::updateOrCreate(
 ['user_id'=>$user->id],
 $data
 );
Mail::to('odukoyasheriff@gmail.com')
 ->send(new JobProfileSubmitted($profile));
 return response()->json($profile);
 }

 
public function update(Request $request, $id)
{
 $user = $request->user();
 $profile = JobProfile::where('id', $id)
 ->where('user_id', $user->id)
 ->firstOrFail();
 $validated = $request->validate([
 'type' => 'required|in:creator,finder',
 // Creator
 'company_name' => 'nullable|string|max:255',
 'company_logo' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
 'company_type' => 'nullable|in:individual,organisation',
 'organisation_size' => 'nullable|string|max:255',
 'company_location' => 'nullable|string|max:255',
 // Finder
 'full_name' => 'nullable|string|max:255',
 'cv' => 'nullable|mimes:pdf,doc,docx|max:5120',
 'qualifications' => 'nullable|string',
 'portfolio' => 'nullable|string|max:500',
 'certification' => 'nullable|string',
 'skills' => 'nullable|array',
 'skills.*' => 'string|max:100',
 ]);
 /*
 |--------------------------------------------------------------------------
 | Company Logo
 |--------------------------------------------------------------------------
 */
 if ($request->hasFile('company_logo')) {
 if ($profile->company_logo &&
 Storage::disk('public')->exists($profile->company_logo)) {
 Storage::disk('public')->delete($profile->company_logo);
 }
 $validated['company_logo'] = $request
 ->file('company_logo')
 ->store('company_logos', 'public');
 }
 /*
 |--------------------------------------------------------------------------
 | CV
 |--------------------------------------------------------------------------
 */
 if ($request->hasFile('cv')) {
 if ($profile->cv &&
 Storage::disk('public')->exists($profile->cv)) {
 Storage::disk('public')->delete($profile->cv);
 }
 $validated['cv'] = $request
 ->file('cv')
 ->store('cvs', 'public');
 }
 /*
 |--------------------------------------------------------------------------
 | Skills
 |--------------------------------------------------------------------------
 */
 if ($request->filled('skills')) {
 $validated['skills'] = json_encode(
 $request->skills
 );
 }
 $profile->update($validated);
 return response()->json([
 'message' => 'Profile updated successfully.',
 'profile' => $profile->fresh(),
 ]);
}


}