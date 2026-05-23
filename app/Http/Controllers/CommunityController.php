<?php

namespace App\Http\Controllers;
use App\Models\Community;
use App\Models\Chat;
use App\Models\CommunityMember;
use Illuminate\Http\Request;

class CommunityController extends Controller
{
    // CommunityController.php

public function index()
{
    $communities = Chat::where(
        'is_community',
        true
    )->latest()->get();

    return response()->json([
        'communities' => $communities
    ]);
}

public function messages($id)
{
    $messages = Message::with('sender')
        ->where('chat_id', $id)
        ->latest()
        ->take(100)
        ->get()
        ->reverse()
        ->values();

    return response()->json([
        'messages' => $messages
    ]);
}


public function create(Request $request)
{
    $request->validate([
        'community_name' => 'required|string|max:255',
        'community_description' => 'nullable|string',
        'community_image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:20480',
        'only_admin_can_message' => 'nullable|boolean',
        'users' => 'nullable|array',
        'users.*' => 'exists:users,id',
    ]);

    $imagePath = null;

    if ($request->hasFile('community_image')) {
        $imagePath = $request->file('community_image')
            ->store('community_images', 'public');
    }

    $community = Community::create([
        'creator_id' => auth()->id(),
        'owner_id' => auth()->id(),
        'community_name' => $request->community_name,
        'community_description' => $request->community_description,
        'community_image' => $imagePath,
        'only_admin_can_message' => $request->only_admin_can_message ?? true,
    ]);

    // ✅ OWNER
    $community->members()->attach(auth()->id(), [
        'role' => 'owner',
        'can_message' => true,
        'muted' => false,
        'joined_at' => now(),
    ]);

    // ✅ ADD USERS
    if ($request->users) {
        foreach ($request->users as $userId) {

            if ($userId != auth()->id()) {
                $community->members()->attach($userId, [
                    'role' => 'member',
                    'can_message' => true,
                    'muted' => false,
                    'joined_at' => now(),
                ]);
            }
        }
    }

    return response()->json([
        'message' => 'Community created',
        'community' => $community->load('members')
    ]);
}


}
