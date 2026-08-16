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


private function visibilityOption($audience)
{
    return match ($audience) {

        '25' => [
            'badges' => 50,
            'months' => 1,
            'label' => '1/4 of users',
        ],

        '50' => [
            'badges' => 100,
            'months' => 2,
            'label' => '1/2 of users',
        ],

        '75' => [
            'badges' => 200,
            'months' => 3,
            'label' => '3/4 of users',
        ],

        '100' => [
            'badges' => 300,
            'months' => 4,
            'label' => 'All users',
        ],

        default => null,
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


public function unlockVisibility(Request $request, $id)
{
    $request->validate([
        'audience' => 'required|in:25,50,75,100',
    ]);

    $advertisement = Advertisement::where('id', $id)
        ->where('user_id', auth()->id())
        ->firstOrFail();

    if ($advertisement->status !== 'approved') {

        return response()->json([
            'message' => 'Advertisement has not been approved.'
        ], 403);

    }

    $option = $this->visibilityOption(
        $request->audience
    );

    $requiredBadges = $option['badges'];

    $months = $option['months'];

    $totalBadges = $this->totalBadges();

    if ($totalBadges < $requiredBadges) {

        return response()->json([
            'message' =>
                "You need {$requiredBadges} badges to unlock this visibility."
        ], 403);

    }

    UserBadge::create([
        'user_id' => auth()->id(),
        'badges' => -$requiredBadges,
        'source' => 'registration',
    ]);

    $startedAt = now();

    $expiresAt = now()->addMonths($months);

    $advertisement->update([

        'audience' =>
            $request->audience,

        'required_badges' =>
            $requiredBadges,

        'visibility_unlocked' =>
            true,

        'visibility_started_at' =>
            $startedAt,

        'visibility_expires_at' =>
            $expiresAt,

    ]);

    return response()->json([

        'message' =>
            'Advertisement visibility unlocked successfully.',

        'advertisement' =>
            $advertisement->fresh(),

    ], 200);
}


public function myAdvertisement()
{
    $advertisements = Advertisement::where(
        'user_id',
        auth()->id()
    )
    ->latest()
    ->get();

    return response()->json([
        'advertisements' => $advertisements
    ]);
}


public function renewVisibility(Request $request, $id)
{
    $request->validate([
        'audience' => 'required|in:25,50,75,100',
    ]);

    $advertisement = Advertisement::where('id', $id)
        ->where('user_id', auth()->id())
        ->firstOrFail();

    if (!$advertisement->visibility_unlocked) {

        return response()->json([
            'message' =>
                'This advertisement does not have an active visibility plan.'
        ], 422);

    }

    if (
        $advertisement->visibility_expires_at &&
        now()->lt($advertisement->visibility_expires_at)
    ) {

        return response()->json([
            'message' =>
                'This advertisement visibility has not expired yet.'
        ], 422);

    }

    $option = $this->visibilityOption(
        $request->audience
    );

    $requiredBadges = $option['badges'];

    $months = $option['months'];

    $totalBadges = $this->totalBadges();

    if ($totalBadges < $requiredBadges) {

        return response()->json([
            'message' =>
                "You need {$requiredBadges} badges to renew this visibility."
        ], 403);

    }

    UserBadge::create([
        'user_id' => auth()->id(),
        'badges' => -$requiredBadges,
        'source' => 'registration',
    ]);

    $startedAt = now();

    $expiresAt = now()->addMonths($months);

    $advertisement->update([

        'audience' =>
            $request->audience,

        'required_badges' =>
            $requiredBadges,

        'visibility_unlocked' =>
            true,

        'visibility_started_at' =>
            $startedAt,

        'visibility_expires_at' =>
            $expiresAt,

    ]);

    return response()->json([

        'message' =>
            'Advertisement visibility renewed successfully.',

        'advertisement' =>
            $advertisement->fresh(),

    ]);
}


public function deleteVisibility($id)
{
    $advertisement = Advertisement::where('id', $id)
        ->where('user_id', auth()->id())
        ->firstOrFail();

    // Delete uploaded media
    if (
        $advertisement->media &&
        Storage::disk('public')->exists(
            $advertisement->media
        )
    ) {
        Storage::disk('public')->delete(
            $advertisement->media
        );
    }

    // Permanently delete advertisement
    $advertisement->delete();

    return response()->json([
        'message' =>
            'Advertisement deleted successfully.',
    ], 200);
}

}