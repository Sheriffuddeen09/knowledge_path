<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ProfileVisibilityController extends Controller
{
   public function update(Request $request, $userId)
{
    if (auth()->user()->role !== 'admin') {
        return response()->json(['message' => 'Unauthorized'], 403);
    }

    $visibility = ProfileVisibility::updateOrCreate(
        ['user_id' => $userId],
        $request->only([
            'profile_visible',
            'show_email',
            'show_dob',
            'show_first_name',
            'show_last_name',
            'show_phone',
            'show_location',
            'show_gender',
            'show_role',
            'show_password',
        ])
    );

    return response()->json([
        'message' => 'Visibility updated',
        'data' => $visibility
    ]);
}

}
