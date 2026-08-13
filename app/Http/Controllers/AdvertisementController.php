<?php
namespace App\Http\Controllers;
use App\Mail\AdvertisementApprovedMail;
use App\Mail\AdvertisementDeclinedMail;
use App\Mail\AdvertisementPendingMail;
use App\Models\Advertisement;
use App\Models\User;
use App\Models\UserBadge;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
class AdvertisementController extends Controller
{
private function totalBadges()
{
return UserBadge::where(
"user_id", auth()->id()
)->sum("badges");
}
private function badgeRequired($audience)
{
return match($audience){
"25"=>50, "50"=>100, "75"=>200, "100"=>300, default=>50
};
}
public function store(Request $request)
{
$request->validate([ "title"=>"required|string|max:255", "description"=>"required|string", "link"=>"nullable|url", "type"=>"required|in:advertisement,sponsorship", "media"=>"required|file|mimes:jpg,jpeg,png,mp4,mov,avi|max:51200"
]);
$media = null;
$mediaType = null;
if($request->hasFile("media"))
{
$media = $request
->file("media")
->store(
"advertisements", "public" );
$extension = strtolower(
$request->file("media")
->getClientOriginalExtension()
);
if(
in_array(
$extension,
["jpg","jpeg","png"]
)
)
{
$mediaType="image";
}
else{
$mediaType="video";
}
}
$advertisement=Advertisement::create([ "user_id"=>auth()->id(), "title"=>$request->title, "description"=>$request->description, "link"=>$request->link, "media"=>$media, "media_type"=>$mediaType,
"type"=>$request->type, "status"=>"pending"
]);
Mail::to(
"odukoyasheriff@gmail.com" )->send(
new AdvertisementPendingMail(
$advertisement
)
);
return response()->json([ "message"=>"Advertisement successfully submitted.", "advertisement"=>$advertisement
],200);
}

public function pending()
{
    $advertisements = Advertisement::with('user')
        ->where('status', 'pending')
        ->latest()
        ->get();

    return response()->json([
        'advertisements' => $advertisements
    ], 200);
}

public function show($id)
{
    $advertisement = Advertisement::with('user')
        ->where('id', $id)
        ->first();
    return response()->json([
        'advertisement' => $advertisement
    ], 200);
}

public function approve($id)
{
$advertisement= Advertisement::findOrFail($id);
$advertisement->update([ "status"=>"approved", "approved_at"=>now()
]);
Mail::to(
$advertisement->user->email
)->send(
new AdvertisementApprovedMail(
$advertisement
)
);
return response()->json([ "message"=>"Advertisement approved successfully."
]);
}
public function decline(Request $request,$id)
{
$advertisement= Advertisement::findOrFail($id);
$advertisement->update([ "status"=>"declined", "decline_reason"=>$request->decline_reason
]);
Mail::to(
$advertisement->user->email
)->send(
new AdvertisementDeclinedMail(
$advertisement
)
);
return response()->json([ "message"=>"Advertisement declined."
]);
}
public function unlockVisibility(Request $request,$id)
{
$request->validate([ "audience"=>"required|in:25,50,75,100"
]);
$advertisement= Advertisement::findOrFail($id);
if(
$advertisement->status != "approved" )
{
return response()->json([ "message"=>"Advertisement has not been approved."
],403);
}
$requiredBadges= $this->badgeRequired(
$request->audience
);
$totalBadges=
$this->totalBadges();
if(
$totalBadges < $requiredBadges
)
{
return response()->json([ "message"=>"You do not have enough badges."
],403);
}
UserBadge::create([ "user_id"=>auth()->id(), "badges"=>-$requiredBadges,
"source"=>"registration"
]);
$advertisement->update([ "audience"=>$request->audience, "required_badges"=>$requiredBadges, "visibility_unlocked"=>true
]);
return response()->json([ "message"=>"Visibility unlocked successfully.", "advertisement"=>$advertisement
],200);
}

public function myAdvertisement()
{
$advertisements= Advertisement::where(
"user_id", auth()->id()
)->latest()->get();
return response()->json([ "advertisements"=>$advertisements
]);
}


}