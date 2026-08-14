<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\UserBadge;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProductVisibilityController extends Controller
{

    private function badgeRequired($visibility)
    {
        return match ((string) $visibility) {

            'location' => 0,

            '25' => 80,

            '50' => 180,

            '75' => 270,

            '100' => 300,

            default => null,

        };
    }

    private function totalBadges()
    {
        return UserBadge::where(
            'user_id',
            auth()->id()
        )->sum('badges');
    }

    public function show($id)
    {
        $product = Product::with([
            'images',
            'category',
            'user'
        ])
        ->where('id', $id)
        ->where('user_id', auth()->id())
        ->firstOrFail();

        return response()->json([
            'product' => $product,
            'current_visibility' => $product->visibility,
            'visibility_unlocked' => $product->visibility_unlocked,
            'visibility_badges' => $product->visibility_badges,
            'available_badges' => $this->totalBadges(),
        ]);
    }


    public function update(Request $request, $id)
    {
        $request->validate([
            'visibility' => 'required|in:25,50,75,100',
        ]);


        $product = Product::where('id', $id)
            ->where('user_id', auth()->id())
            ->firstOrFail();

        $requiredBadges = $this->badgeRequired(
            $request->visibility
        );

        $currentVisibility =
            $product->visibility ?? 'location';


        $visibilityRank = [

            'location' => 0,

            '25' => 1,

            '50' => 2,

            '75' => 3,

            '100' => 4,

        ];


        if (
            isset($visibilityRank[$currentVisibility]) &&
            $visibilityRank[$request->visibility]
                <= $visibilityRank[$currentVisibility]
        ) {

            return response()->json([

                'message' =>
                    'You cannot select this visibility because your product already has this or a higher visibility level.'

            ], 422);

        }

        $totalBadges = $this->totalBadges();


        if ($totalBadges < $requiredBadges) {

            return response()->json([

                'message' =>
                    "You need {$requiredBadges} badges to unlock this visibility.",

                'required_badges' => $requiredBadges,

                'available_badges' => $totalBadges,

            ], 403);
        }


        DB::transaction(function () use (
            $product,
            $request,
            $requiredBadges
        ) {


            UserBadge::create([

                'user_id' => auth()->id(),

                'badges' => -$requiredBadges,

                'source' => 'product_visibility',

            ]);

            $product->update([

                'visibility' =>
                    $request->visibility,

                'visibility_badges' =>
                    $requiredBadges,

                'visibility_unlocked' =>
                    true,

                'visibility_unlocked_at' =>
                    now(),

            ]);

        });


        $product->refresh();


        return response()->json([

            'message' =>
                'Product visibility successfully upgraded.',

            'product' => $product,

            'visibility' =>
                $product->visibility,

            'badges_spent' =>
                $requiredBadges,

            'remaining_badges' =>
                $this->totalBadges(),

        ]);
    }
}