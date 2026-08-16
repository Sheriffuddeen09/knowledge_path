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


    
    public function upgrade(
        Request $request,
        $id
    ) {

        $request->validate([
            'visibility' =>
                'required|in:25,50,75,100',
        ]);

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

        $plan =
            $this->visibilityPlan(
                $request->visibility
            );


        $totalBadges =
            UserBadge::where(
                'user_id',
                $user->id
            )->sum('badges');

        if (
            $totalBadges <
            $plan['badges']
        ) {

            return response()->json([
                'message' =>
                    "You need {$plan['badges']} badges."
            ], 403);

        }


        if (
            $product->visibility_unlocked &&
            $product->visibility_expires_at &&
            now()->lessThan(
                $product->visibility_expires_at
            )
        ) {

            return response()->json([
                'message' =>
                    'This product visibility is still active.'
            ], 403);

        }

        DB::transaction(function () use (
            $product,
            $user,
            $plan,
            $request
        ) {

           

            UserBadge::create([
                'user_id' =>
                    $user->id,

                'badges' =>
                    -$plan['badges'],

                'source' =>
                    'registration',
            ]);


            $product->update([

                'visibility' =>
                    $request->visibility,

                'visibility_unlocked' =>
                    true,

                'visibility_badges' =>
                    $plan['badges'],

                'visibility_started_at' =>
                    now(),

                'visibility_expires_at' =>
                    now()->addMonths(
                        $plan['months']
                    ),

            ]);

        });


        return response()->json([

            'message' =>
                'Product visibility updated successfully.',

            'product' =>
                $product->fresh()->load('images'),

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