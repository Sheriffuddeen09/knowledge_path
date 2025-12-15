<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class ProfileController extends Controller
{
    /**
     * View profile
     */
    public function show(Request $request, $id = null)
{
    $authUser = $request->user();
    $user = $id ? User::findOrFail($id) : $authUser;

    if ($authUser && $authUser->id === $user->id) {
        return response()->json([
            ...$user->toArray(),
            'visibility' => $user->visibility ?? [
                'email' => true,
                'phone' => true,
                'dob' => true,
                'location' => true,
                'gender' => true,
            ]
        ]);
    }

    $visibility = $user->visibility ?? [];

    return response()->json([
        'id'         => $user->id,
        'first_name' => $user->first_name,
        'last_name'  => $user->last_name,
        'role'       => $user->role,
        'email'      => ($visibility['email'] ?? false) ? $user->email : null,
        'phone'      => ($visibility['phone'] ?? false) ? $user->phone : null,
        'gender'     => ($visibility['gender'] ?? false) ? $user->gender : null,
        'dob'        => ($visibility['dob'] ?? false) ? $user->dob : null,
        'location'   => ($visibility['location'] ?? false) ? $user->location : null,
        'visibility' => $visibility
    ]);
}

    /**
     * Update own profile ONLY
     */
    public function update(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'first_name' => 'sometimes|string|max:255',
            'last_name'  => 'sometimes|string|max:255',
            'email'      => 'sometimes|email|unique:users,email,' . $user->id,
            'phone'      => 'nullable|string|max:20',
            'gender'     => 'nullable|string',
            'dob'        => 'nullable|date',
            'location'   => 'nullable|string|max:255',
            'password'   => 'nullable|string|min:6|confirmed',
        ]);

        if (isset($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        }

        $user->update($validated);

        return response()->json([
            'message' => 'Profile updated successfully',
            'user' => $user->makeHidden(['password'])
        ]);
    }

    /**
     * Update visibility (own profile only)
     */
    public function updateVisibility(Request $request)
{
    $request->validate([
        'visibility' => 'required|array',
    ]);

    $user = $request->user();

    $user->visibility = $request->visibility;
    $user->save(); // Use save() to ensure casts work

    return response()->json([
        'message' => 'Visibility updated successfully',
        'visibility' => $user->visibility
    ]);
}
}
