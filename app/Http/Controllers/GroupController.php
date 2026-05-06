<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Message;
use App\Models\Chat;
use App\Models\User;
use Illuminate\Support\Str;



class GroupController extends Controller
{
    

public function createGroup(Request $request)
{
    $request->validate([
        'name' => 'required|string',
        'users' => 'required|array',
        'image' => 'nullable|image|mimes:jpg,jpeg,png,webp',
    ]);

    $imagePath = null;

    if ($request->hasFile('image')) {
        $imagePath = $request->file('image')->store('group_images', 'public');
    }

    $chat = Chat::create([
        'type' => 'group',
        'name' => $request->name,
        'image' => $imagePath,
        'created_by' => auth()->id(),
        'only_admin_send' => $request->only_admin_send ?? 0,
    ]);

    // ✅ ADMIN (MUST ALWAYS BE APPROVED)
    $chat->users()->attach(auth()->id(), [
        'role' => 'admin',
        'status' => 'approved', // 🔥 FIX
    ]);

    // ✅ OTHER USERS (OPTIONAL: make pending or approved)
    foreach ($request->users as $userId) {
        $chat->users()->attach($userId, [
            'role' => 'member',
            'status' => 'approved', // 🔥 choose behavior
        ]);
    }

    return response()->json($chat);
}


public function updateGroup(Request $request, Chat $chat)
{
    $authId = auth()->id();

    // ✅ Check membership
    $membership = DB::table('chat_user')
        ->where('chat_id', $chat->id)
        ->where('user_id', $authId)
        ->first();

    if (!$membership) {
        return response()->json([
            'message' => 'You are not a member of this group'
        ], 403);
    }

    $isAdmin = $membership->role === 'admin';

    if ($request->filled('name')) {

        $oldName = $chat->name;
        $chat->name = $request->name;
        $chat->save();

        Message::create([
            'chat_id'   => $chat->id,
            'sender_id' => $authId,
            'type'      => 'system',
            'message'   => "Group name changed from '{$oldName}' to '{$request->name}'",
        ]);
    }

    if ($request->hasFile('image')) {

        $path = $request->file('image')->store('group_images', 'public');

        $chat->image = $path;
        $chat->save();

        Message::create([
            'chat_id'   => $chat->id,
            'sender_id' => $authId,
            'type'      => 'system',
            'message'   => 'Group image was updated',
        ]);
    }

    if ($request->has('only_admin_send')) {

        if (!$isAdmin) {
            return response()->json([
                'message' => 'Only admin can change this setting'
            ], 403);
        }

        $chat->only_admin_send = $request->only_admin_send;
        $chat->save();

        Message::create([
            'chat_id'   => $chat->id,
            'sender_id' => $authId,
            'type'      => 'system',
            'message'   => $chat->only_admin_send
                ? 'Only admins can send messages now'
                : 'All members can send messages now',
        ]);
    }

    if ($request->has('is_locked')) {

        if (!$isAdmin) {
            return response()->json([
                'message' => 'Only admin can lock chat'
            ], 403);
        }

        $chat->is_locked = $request->is_locked;
        $chat->save();

        Message::create([
            'chat_id'   => $chat->id,
            'sender_id' => $authId,
            'type'      => 'system',
            'message'   => $chat->is_locked
                ? 'Chat was locked'
                : 'Chat was unlocked',
        ]);
    }

    return response()->json([
        'success' => true,
        'chat' => [
            ...$chat->fresh()->toArray(),
            'image_url' => $chat->image
                ? asset('storage/' . $chat->image)
                : null
        ]
    ]);
}

public function toggleAdmin(Request $request, Chat $chat)
{
    $request->validate([
        'user_id' => 'required|exists:users,id',
    ]);

    $authId   = auth()->id();
    $targetId = $request->user_id;

    // ✅ Ensure it's a group
    if ($chat->type !== 'group') {
        return response()->json(['message' => 'Not a group'], 400);
    }

    // ✅ Check if auth user is admin
    $isAdmin = DB::table('chat_user')
        ->where('chat_id', $chat->id)
        ->where('user_id', $authId)
        ->where('role', 'admin')
        ->exists();

    if (!$isAdmin) {
        return response()->json(['message' => 'Only admins can perform this action'], 403);
    }

    // ❌ Prevent self-demotion
    if ($authId == $targetId) {
        return response()->json([
            'message' => 'You cannot change your own admin role'
        ], 400);
    }

    // ✅ Ensure target is in group
    $exists = DB::table('chat_user')
        ->where('chat_id', $chat->id)
        ->where('user_id', $targetId)
        ->exists();

    if (!$exists) {
        return response()->json([
            'message' => 'User not in group'
        ], 404);
    }

    // ✅ Get current role
    $currentRole = DB::table('chat_user')
        ->where('chat_id', $chat->id)
        ->where('user_id', $targetId)
        ->value('role');

    // 🔥 Prevent removing LAST admin
    if ($currentRole === 'admin') {
        $adminCount = DB::table('chat_user')
            ->where('chat_id', $chat->id)
            ->where('role', 'admin')
            ->count();

        if ($adminCount <= 1) {
            return response()->json([
                'message' => 'Cannot remove the last admin'
            ], 400);
        }
    }

    // ✅ Toggle role
    $newRole = $currentRole === 'admin' ? 'member' : 'admin';

    DB::table('chat_user')
        ->where('chat_id', $chat->id)
        ->where('user_id', $targetId)
        ->update(['role' => $newRole]);

    // ✅ SYSTEM MESSAGE
    Message::create([
        'chat_id'   => $chat->id,
        'sender_id' => $authId,
        'type'      => 'system',
        'message'   => $newRole === 'admin'
            ? 'User was promoted to admin'
            : 'User was removed as admin',
    ]);

    return response()->json([
        'success' => true,
        'user_id' => $targetId,
        'role'    => $newRole
    ]);
}


public function removeMember(Request $request, $chatId)
{
    $authId = auth()->id();

    $chat = Chat::findOrFail($chatId);

    if (!$chat->isAdmin($authId)) {
        return response()->json([
            'message' => 'Unauthorized'
        ], 403);
    }

    $request->validate([
        'user_id' => 'required|exists:users,id'
    ]);

    DB::table('chat_user')
        ->where('chat_id', $chatId)
        ->where('user_id', $request->user_id)
        ->update([
            'status' => 'removed',
            'updated_at' => now(),
        ]);

    // ✅ SYSTEM MESSAGE
    $user = DB::table('users')->find($request->user_id);

    Message::create([
        'chat_id' => $chatId,
        'sender_id' => $authId,
        'type' => 'system',
        'message' => "{$user->first_name} has been removed",
    ]);

    return response()->json([
        'success' => true,
        'message' => 'Member removed'
    ]);
}

public function addMember(Request $request, Chat $chat)
{
    $request->validate([
        'user_id' => 'required|exists:users,id',
    ]);

    $authId = auth()->id();

    $authMember = DB::table('chat_user')
        ->where('chat_id', $chat->id)
        ->where('user_id', $authId)
        ->first();

    if (!$authMember) {
        return response()->json(['message' => 'Not a member'], 403);
    }

    $isAdmin = $authMember->role === 'admin';

    $existing = DB::table('chat_user')
    ->where('chat_id', $chat->id)
    ->where('user_id', $request->user_id)
    ->first();

        $user = DB::table('users')->find($request->user_id);

        if ($existing) {

            // 🚫 already active
            if (in_array($existing->status, ['approved', 'pending'])) {
                return response()->json([
                    'message' => 'Already in group or pending'
                ], 409);
            }

            // 🔁 re-add (removed, rejected, left)
            if (in_array($existing->status, ['removed', 'rejected', 'left'])) {

                DB::table('chat_user')
                    ->where('id', $existing->id)
                    ->update([
                        'status' => $isAdmin ? 'approved' : 'pending',
                        'role' => 'member',
                        'updated_at' => now(),
                    ]);

                // ✅ SYSTEM MESSAGE
                Message::create([
                    'chat_id' => $chat->id,
                    'sender_id' => auth()->id(),
                    'type' => 'system',
                    'message' => "{$user->first_name} has been added",
                ]);

                return response()->json([
                    'message' => 'User re-added successfully'
                ]);
            }
        }

        // ✅ ONLY insert if truly new
        DB::table('chat_user')->insert([
            'chat_id'    => $chat->id,
            'user_id'    => $request->user_id,
            'role'       => 'member',
            'status'     => $isAdmin ? 'approved' : 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // ✅ SYSTEM MESSAGE
        Message::create([
            'chat_id' => $chat->id,
            'sender_id' => auth()->id(),
            'type' => 'system',
            'message' => "{$user->first_name} has been added",
        ]);

    return response()->json([
        'message' => $isAdmin
            ? 'Member added'
            : 'Request sent, awaiting approval'
    ]);
}



public function joinByInvite($token)
{
    $chat = Chat::where('invite_token', $token)->first();

    if (!$chat) {
        return response()->json(['message' => 'Invalid link'], 404);
    }

    $userId = auth()->id();

    $existing = DB::table('chat_user')
        ->where('chat_id', $chat->id)
        ->where('user_id', $userId)
        ->first();

    // 🔥 If already active or pending → block
    if ($existing && in_array($existing->status, ['pending', 'approved'])) {
        return response()->json(['message' => 'Already joined or request pending'], 409);
    }

    // 🔥 If rejected or removed → allow rejoin (update instead of insert)
    if ($existing && in_array($existing->status, ['rejected', 'removed'])) {

        DB::table('chat_user')
            ->where('id', $existing->id)
            ->update([
                'status' => 'pending',
                'role' => 'member',
                'updated_at' => now(),
            ]);

        return response()->json([
            'message' => 'Re-join request sent. Awaiting approval.'
        ]);
    }

    // 🔥 NEW USER
    DB::table('chat_user')->insert([
        'chat_id'    => $chat->id,
        'user_id'    => $userId,
        'role'       => 'member',
        'status'     => 'pending',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return response()->json([
        'message' => 'Request sent. Awaiting admin approval.'
    ]);
}

public function generateInviteLink(Chat $chat)
{
    $authId = auth()->id();

    $isMember = DB::table('chat_user')
        ->where('chat_id', $chat->id)
        ->where('user_id', $authId)
        ->exists();

    if (!$isMember) {
        return response()->json(['message' => 'Unauthorized'], 403);
    }

    // 🔥 ALWAYS GENERATE NEW TOKEN
    $chat->invite_token = Str::random(40);
    $chat->save();

    return response()->json([
        'invite_link' => config('app.frontend_url') . "/invite/group/" . $chat->invite_token
    ]);
}


    public function approveMember(Request $request, Chat $chat)
{
    $authId = auth()->id();

    $exists = DB::table('chat_user')
        ->where('chat_id', $chat->id)
        ->where('user_id', $request->user_id)
        ->where('status', 'pending')
        ->exists();

    if (!$exists) {
        return response()->json(['message' => 'No pending request found'], 404);
    }

    $isAdmin = DB::table('chat_user')
        ->where('chat_id', $chat->id)
        ->where('user_id', $authId)
        ->where('role', 'admin')
        ->exists();

    if (!$isAdmin) {
        return response()->json(['message' => 'Unauthorized'], 403);
    }

    DB::table('chat_user')
        ->where('chat_id', $chat->id)
        ->where('user_id', $request->user_id)
        ->update([
            'status' => 'approved'
        ]);

    // ✅ ADD SYSTEM MESSAGE
    $user = DB::table('users')->find($request->user_id);

    Message::create([
        'chat_id' => $chat->id,
        'sender_id' => $authId,
        'type' => 'system',
        'message' => "{$user->first_name} has been added",
    ]);

    return response()->json([
        'message' => 'Member approved'
    ]);
}

public function rejectMember(Request $request, Chat $chat)
{
    $authId = auth()->id();

    $isAdmin = DB::table('chat_user')
        ->where('chat_id', $chat->id)
        ->where('user_id', $authId)
        ->where('role', 'admin')
        ->exists();

    if (!$isAdmin) {
        return response()->json(['message' => 'Unauthorized'], 403);
    }

    DB::table('chat_user')
        ->where('chat_id', $chat->id)
        ->where('user_id', $request->user_id)
        ->update([
            'status' => 'rejected'
        ]);

    // ✅ OPTIONAL
    $user = DB::table('users')->find($request->user_id);

    Message::create([
        'chat_id' => $chat->id,
        'sender_id' => $authId,
        'type' => 'system',
        'message' => "{$user->first_name} request was rejected",
    ]);

    return response()->json([
        'message' => 'Request rejected'
    ]);
}

public function pendingMembers(Chat $chat)
{
    $authId = auth()->id();

    $isAdmin = DB::table('chat_user')
        ->where('chat_id', $chat->id)
        ->where('user_id', $authId)
        ->where('role', 'admin')
        ->exists();

    if (!$isAdmin) {
        return response()->json(['message' => 'Unauthorized'], 403);
    }

    return DB::table('chat_user')
        ->join('users', 'users.id', '=', 'chat_user.user_id')
        ->where('chat_user.chat_id', $chat->id)
        ->where('chat_user.status', 'pending')
        ->select('users.id', 'users.first_name', 'users.last_name')
        ->get();
}


public function pendingCount(Chat $chat)
{
    $authId = auth()->id();

    $isAdmin = DB::table('chat_user')
        ->where('chat_id', $chat->id)
        ->where('user_id', $authId)
        ->where('role', 'admin')
        ->exists();

    if (!$isAdmin) {
        return response()->json(['message' => 'Unauthorized'], 403);
    }

    $count = DB::table('chat_user')
        ->where('chat_id', $chat->id)
        ->where('status', 'pending')
        ->count();

    return response()->json([
        'count' => $count
    ]);
}




public function exitGroup(Chat $chat)
{
    $userId = auth()->id();

    $member = DB::table('chat_user')
        ->where('chat_id', $chat->id)
        ->where('user_id', $userId)
        ->first();

    if (!$member) {
        return response()->json(['message' => 'Not a member'], 403);
    }

    $isAdmin = $member->role === 'admin';

    // ❌ DO NOT DELETE
    // ✅ MARK AS LEFT
    DB::table('chat_user')
        ->where('chat_id', $chat->id)
        ->where('user_id', $userId)
        ->update([
            'status' => 'left',
            'updated_at' => now()
        ]);

    // 🔥 system message
    Message::create([
        'chat_id' => $chat->id,
        'sender_id' => $userId,
        'type' => 'system',
        'message' => "{$user->first_name} has left the group",
    ]);

    // ✅ assign new admin if needed
    if ($isAdmin) {
        $newAdmin = DB::table('chat_user')
            ->where('chat_id', $chat->id)
            ->where('status', 'approved') // 🔥 only active users
            ->first();

        if ($newAdmin) {
            DB::table('chat_user')
                ->where('chat_id', $chat->id)
                ->where('user_id', $newAdmin->user_id)
                ->update(['role' => 'admin']);

            Message::create([
                'chat_id' => $chat->id,
                'sender_id' => $userId,
                'type' => 'system',
                'message' => 'A new admin was assigned',
            ]);
        }
    }

    return response()->json(['message' => 'Exited group']);
}


}
