<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\AdminFriendRequest;
use Illuminate\Support\Facades\Mail;
use App\Mail\AdminFriendRequested;
use App\Mail\AdminFriendAccepted;
use App\Mail\AdminFriendDeclined;
use App\Models\Chat;
use App\Models\User;




class AdminFriendController extends Controller

{
public function adminsToAdd(Request $request)
{
    $user = $request->user();

    if ($user->role !== 'admin') {
        return response()->json(['admins' => []]);
    }

    $admins = User::where('role', 'admin')
        ->where('id', '!=', $user->id)

        // ❌ EXCLUDE anyone with pending or accepted request (any direction)
        ->whereNotExists(function ($q) use ($user) {
            $q->selectRaw(1)
              ->from('admin_friend_requests')
              ->whereIn('status', ['pending', 'accepted'])
              ->where(function ($x) use ($user) {
                  $x->whereColumn('admin_friend_requests.user_id', $user->id)
                    ->whereColumn('admin_friend_requests.admin_id', 'users.id')
                  ->orWhere(function ($y) use ($user) {
                      $y->whereColumn('admin_friend_requests.user_id', 'users.id')
                        ->whereColumn('admin_friend_requests.admin_id', $user->id);
                  });
              });
        })

        ->get();

    return response()->json([
        'admins' => $admins
    ]);
}


public function sendRequest(Request $request)
{
    $user = $request->user();
    $adminId = $request->admin_id;

    if ($user->id == $adminId) {
        return response()->json([
            'message' => 'You cannot add yourself'
        ], 422);
    }

    // Find any existing request between both users
    $existing = AdminFriendRequest::where(function ($q) use ($user, $adminId) {
        $q->where('user_id', $user->id)
          ->where('admin_id', $adminId);
    })->orWhere(function ($q) use ($user, $adminId) {
        $q->where('user_id', $adminId)
          ->where('admin_id', $user->id);
    })->first();

    /**
     * 🚫 Already pending → block
     */
    if ($existing && $existing->status === 'pending') {
        return response()->json([
            'message' => 'Request already pending'
        ], 409);
    }

    /**
     * 🔄 Previously declined
     */
    if ($existing && $existing->status === 'declined') {

        // ❗ Delete old request completely
        $existing->delete();
    }

    /**
     * ✅ Create NEW request with CORRECT direction
     */
    $requestModel = AdminFriendRequest::create([
        'user_id' => $user->id,       // sender
        'admin_id' => $adminId,   // receiver
        'status' => 'pending',
        'hidden_for_requester' => false,
        'hidden_for_requested' => false,
    ]);


    // ✅ SAFE MAIL (NULL CHECK)
    if ($requestModel->admin) {
        Mail::to($requestModel->admin->email)
            ->send(new AdminFriendRequested($requestModel));
    }

    return response()->json([
        'message' => 'Request sent',
        'request' => $requestModel
    ]);
}



    // Get requests for the logged-in admin
    public function allRequests(Request $request)
{
    $user = $request->user();

    if ($user->role !== 'admin') {
        return response()->json(['requests' => []]);
    }

    $requests = AdminFriendRequest::with('requester')
        ->where('admin_id', $user->id)
        ->where('status', 'pending')
        ->where('hidden_for_requested', false)
        ->latest()
        ->get();

    return response()->json(['requests' => $requests]);
}




    public function respond(Request $request, $id)
{
    $admin = $request->user();

    $requestModel = AdminFriendRequest::where('id', $id)
        ->where('admin_id', $admin->id)
        ->firstOrFail();

    if (!in_array($request->action, ['accepted', 'declined'])) {
        return response()->json(['message' => 'Invalid action'], 422);
    }

    if ($request->action === 'accepted') {

    $requestModel->update([
        'status' => 'accepted'
    ]);

    [$one, $two] = collect([
        $requestModel->user_id,
        $requestModel->admin_id
    ])->sort()->values();

    Chat::firstOrCreate([
        'user_one_id' => $one,
        'user_two_id' => $two,
        'type' => 'admin_admin',
    ]);
    }


    if ($request->action === 'accepted') {

        $requestModel->update([
            'status' => 'accepted'
        ]);

        if ($requestModel->requester) {
            Mail::to($requestModel->requester->email)
                ->send(new AdminFriendAccepted($requestModel));
        }
    }

    if ($request->action === 'declined') {

        $requestModel->update([
            'status' => 'declined'
        ]);

        if ($requestModel->requester) {
            Mail::to($requestModel->requester->email)
                ->send(new AdminFriendDeclined($requestModel));
        }
    }

    

    return response()->json([
        'message' => 'Request handled',
        'status'  => $requestModel->status
    ]);
}



    // Get requests sent by the admin
    public function myRequests(Request $request)
{
    $user = $request->user();

    $requests = AdminFriendRequest::with('admin')
        ->where('user_id', $user->id)
        ->where('status', 'pending')
        ->where('hidden_for_requester', false)
        ->latest()
        ->get();

    return response()->json(['requests' => $requests]);
}



public function relationshipStatus(Request $request, $adminId)
{
    $user = $request->user();

    $relation = AdminFriendRequest::where(function ($q) use ($user, $adminId) {
        $q->where('user_id', $user->id)
          ->where('admin_id', $adminId);
    })->orWhere(function ($q) use ($user, $adminId) {
        $q->where('user_id', $adminId)
          ->where('admin_id', $user->id);
    })->first();

    if (!$relation) {
        return response()->json([
            'status' => 'none'
        ]);
    }

    return response()->json([
        'status' => $relation->status,
        'direction' => $relation->user_id === $user->id
            ? 'sent'
            : 'received',
        'chat_id' => $relation->status === 'accepted'
            ? optional(
                Chat::whereJsonContains('participants', [$user->id, $adminId])->first()
              )->id
            : null
    ]);
}


public function removeTemporarily($id)
{
    $request = AdminFriendRequest::where(function ($q) {
        $q->where('user_id', auth()->id())
          ->orWhere('admin_id', auth()->id());
    })
    ->where(function ($q) use ($id) {
        $q->where('user_id', $id)
          ->orWhere('admin_id', $id);
    })
    ->firstOrFail();

    $request->update([
        'removed_until' => now()->addDays(30)
    ]);

    return response()->json(['message' => 'Removed for 30 days']);
}


public function accept($id)
{
    $friend = AdminFriendRequest::where('id', $id)
        ->where('receiver_id', auth()->id())
        ->firstOrFail();

    $friend->update([
        'status' => 'accepted'
    ]);

    return response()->json([
        'message' => 'Friend request accepted'
    ]);
}


public function relation(Request $request, $profileId)
{
    $userId = $request->user()->id;

    $relation = AdminFriendRequest::where(function ($q) use ($userId, $profileId) {
        $q->where('sender_id', $userId)
          ->where('receiver_id', $profileId);
    })->orWhere(function ($q) use ($userId, $profileId) {
        $q->where('sender_id', $profileId)
          ->where('receiver_id', $userId);
    })->first();

    if (!$relation) {
        return response()->json([
            'status' => 'none',
            'direction' => null
        ]);
    }

    return response()->json([
        'status' => $relation->status,
        'direction' =>
            $relation->status === 'pending'
                ? ($relation->sender_id === $userId ? 'sent' : 'received')
                : null
    ]);
}



public function show($id, Request $request)
{
    $authId = $request->user()->id;

    $user = User::findOrFail($id);

    // 🔑 CHECK BOTH DIRECTIONS
    $relation = AdminFriendRequest::where(function ($q) use ($authId, $id) {
        $q->where('user_id', $authId)
          ->where('admin_id', $id);
    })->orWhere(function ($q) use ($authId, $id) {
        $q->where('user_id', $id)
          ->where('admin_id', $authId);
    })->first();

    return response()->json([
        'admin' => $user,
        'status'  => $relation?->status ?? 'none',
    ]);
}

}
