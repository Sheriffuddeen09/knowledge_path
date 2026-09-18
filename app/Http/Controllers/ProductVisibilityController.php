<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\UserBadge;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProductVisibilityController extends Controller
{
    

    private function visibilityPlan($visibility)
{
    return match ((string) $visibility) {

        '25' => [
            'badges' => 80,
            'months' => 1,
            'label' => '1/4 of locations',
        ],

        '50' => [
            'badges' => 180,
            'months' => 2,
            'label' => '1/2 of locations',
        ],

        '75' => [
            'badges' => 270,
            'months' => 3,
            'label' => '3/4 of locations',
        ],

        '100' => [
            'badges' => 300,
            'months' => 4,
            'label' => 'All locations',
        ],

        default => null,
    };
}


    public function upgrade(Request $request, $id)
{
    $request->validate([
        'visibility' => 'required|in:25,50,75,100',
    ]);

    $user = $request->user();

    $product = Product::where('id', $id)
        ->where('user_id', $user->id)
        ->firstOrFail();

    $plan = $this->visibilityPlan($request->visibility);

    if (!$plan) {
        return response()->json([
            'message' => 'Invalid visibility plan.'
        ], 422);
    }

    /*
    |--------------------------------------------------------------------------
    | Do not allow another upgrade while current visibility is active
    |--------------------------------------------------------------------------
    */

    if (
        $product->visibility_unlocked &&
        $product->visibility_expires_at &&
        now()->lessThan($product->visibility_expires_at)
    ) {
        return response()->json([
            'message' => 'This product visibility is still active.'
        ], 403);
    }

    $totalBadges = UserBadge::where('user_id', $user->id)
        ->sum('badges');

    if ($totalBadges < $plan['badges']) {
        return response()->json([
            'message' => "You need {$plan['badges']} badges."
        ], 403);
    }

    DB::transaction(function () use (
        $product,
        $user,
        $plan,
        $request
    ) {

        UserBadge::create([
            'user_id' => $user->id,
            'badges' => -$plan['badges'],
            'source' => 'registration',
        ]);

        $startedAt = now();

        $expiresAt = now()->addMonths($plan['months']);

        $product->update([
            'visibility' => $request->visibility,

            'visibility_unlocked' => true,

            'visibility_badges' => $plan['badges'],

            'visibility_started_at' => $startedAt,

            'visibility_expires_at' => $expiresAt,
        ]);
    });

    $product->refresh();

    return response()->json([
        'message' => 'Product visibility updated successfully.',

        'product' => $product->load('images'),

        'visibility' => $product->visibility,

        'visibility_started_at' => $product->visibility_started_at,

        'visibility_expires_at' => $product->visibility_expires_at,
    ]);
}

    public function index(Request $request)
    {
        $user = $request->user();

        $products = Product::with('images')
            ->withCount('reviews')
            ->where('user_id', $user->id)
            ->latest()
            ->get();

        $products->transform(function ($product) {

            $expired = false;

            if (
                $product->visibility_unlocked &&
                $product->visibility_expires_at
            ) {

                $expired =
                    now()->greaterThan(
                        $product->visibility_expires_at
                    );

            }

            $product->visibility_expired =
                $expired;

            $product->visibility_active =
                $product->visibility_unlocked &&
                !$expired;

            if ($product->visibility) {

                $plan =
                    $this->visibilityPlan(
                        $product->visibility
                    );

                $product->visibility_label =
                    $plan['label'] ?? null;

                $product->visibility_months =
                    $plan['months'] ?? null;

            } else {

                $product->visibility_label =
                    'Only your location';

                $product->visibility_months =
                    0;

            }

            return $product;

        });

        return response()->json([
            'products' => $products,
        ]);
    }


     

    public function destroy(
        Request $request,
        $id
    ) {

        $user = $request->user();

        $product = Product::where(
            'id',
            $id
        )
        ->where(
            'user_id',
            $user->id
        )
        ->firstOrFail();



        if (
            !$product->visibility_expires_at ||
            now()->lessThanOrEqualTo(
                $product->visibility_expires_at
            )
        ) {

            return response()->json([
                'message' =>
                    'You can only delete expired visibility.'
            ], 403);

        }


        $product->update([

            'visibility' =>
                null,

            'visibility_unlocked' =>
                false,

            'visibility_badges' =>
                0,

            'visibility_started_at' =>
                null,

            'visibility_expires_at' =>
                null,

        ]);


        return response()->json([

            'message' =>
                'Product visibility removed successfully.',

            'product' =>
                $product->fresh(),

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

            $visibilityRank = [
                'location' => 0,
                '25' => 1,
                '50' => 2,
                '75' => 3,
                '100' => 4,
            ];

            $currentVisibility = $product->visibility ?? 'location';

            $currentRank = $visibilityRank[$currentVisibility] ?? 0;

            $newRank = $visibilityRank[$request->visibility];

            $isExpired =
                $product->visibility_expires_at &&
                now()->greaterThanOrEqualTo(
                    $product->visibility_expires_at
                );

            if ($newRank < $currentRank) {
                return response()->json([
                    'message' =>
                        'You cannot select a lower visibility level than your current level.',
                ], 422);
            }

            if (
                $newRank === $currentRank &&
                $currentRank > 0 &&
                !$isExpired
            ) {
                return response()->json([
                    'message' =>
                        'Your product already has this visibility level. You can renew it after it expires.',
                ], 422);
            }

            $totalBadges = $this->totalBadges();

            if ($totalBadges < $requiredBadges) {
                return response()->json([
                    'message' =>
                        "You need {$requiredBadges} badges to unlock this visibility.",

                    'required_badges' =>
                        $requiredBadges,

                    'available_badges' =>
                        $totalBadges,
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

                    'source' => 'registration',
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

                    'visibility_started_at' =>
                        now(),

                    'visibility_expires_at' =>
                        now()->addDays(30),
                ]);
            });
        

            $product->refresh();
        
            return response()->json([

                'status' =>
                    true,

                'message' =>
                    $isExpired
                        ? 'Product visibility successfully renewed.'
                        : 'Product visibility successfully upgraded.',

                'product' =>
                    $product,

                'visibility' =>
                    $product->visibility,

                'badges_spent' =>
                    $requiredBadges,

                'remaining_badges' =>
                    $this->totalBadges(),

                'visibility_started_at' =>
                    $product->visibility_started_at,

                'visibility_expires_at' =>
                    $product->visibility_expires_at,

            ]);
        }

}