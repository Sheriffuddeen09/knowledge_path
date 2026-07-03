<?php

namespace App\Http\Controllers;

use App\Models\UserBadge;
use Illuminate\Http\Request;

class UserBadgeController extends Controller
{
    public function badges(Request $request)
    {
        $userId = $request->user()->id;

        return response()->json([
            'total' => UserBadge::where('user_id', $userId)->sum('badges'),

            'assignment' => UserBadge::where('user_id', $userId)
                ->where('source', 'assignment')
                ->sum('badges'),

            'exam' => UserBadge::where('user_id', $userId)
                ->where('source', 'exam')
                ->sum('badges'),
        ]);
    }

    
    public function watchAd(Request $request)
{
    $user = $request->user();

    // add 5 badges
    UserBadge::create([
        'user_id' => $user->id,
        'badges' => 5,
        'source' => 'ads'
    ]);

    $total = UserBadge::where('user_id', $user->id)->sum('badges');

    return response()->json(['total' => $total]);
}


}
