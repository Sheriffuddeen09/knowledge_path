<?php

namespace App\Http\Controllers;

use App\Models\Review;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ReviewController extends Controller
{
    
    public function index($productId)
    {
        $reviews = Review::with('user')
            ->where('product_id', $productId)
            ->latest()
            ->get();

        return response()->json($reviews);
    }



    public function store(
        Request $request,
        $productId
    ) {

        $request->validate([
            'rating' =>
                'required|integer|min:1|max:5',

            'comment' =>
                'required|string|max:1000',
        ]);


        $review = Review::create([

            'product_id' =>
                $productId,

            'user_id' =>
                Auth::id(),

            'rating' =>
                $request->rating,

            'comment' =>
                $request->comment,

        ]);


        $review->load([
            'user',
            'product'
        ]);


        return response()->json(
            $review,
            201
        );
    }


    public function myProductReviews(
        Request $request
    ) {

        $user = $request->user();


        $reviews = Review::with([
            'user:id,first_name,last_name',
            'product:id,user_id,title,location'
        ])


        ->whereHas(
            'product',
            function ($query) use ($user) {

                $query->where(
                    'user_id',
                    $user->id
                );

            }
        )

        ->latest()
        ->get();

        $totalReviews =
            $reviews->count();


        $averageRating =
            $totalReviews > 0
                ? round(
                    $reviews->avg('rating'),
                    1
                )
                : 0;


        $fiveStar =
            $reviews->where(
                'rating',
                5
            )->count();


        $fourStar =
            $reviews->where(
                'rating',
                4
            )->count();


        $threeStar =
            $reviews->where(
                'rating',
                3
            )->count();


        $twoStar =
            $reviews->where(
                'rating',
                2
            )->count();


        $oneStar =
            $reviews->where(
                'rating',
                1
            )->count();


        return response()->json([

            'reviews' =>
                $reviews,

            'total_reviews' =>
                $totalReviews,

            'average_rating' =>
                $averageRating,

            'rating_breakdown' => [

                '5' =>
                    $fiveStar,

                '4' =>
                    $fourStar,

                '3' =>
                    $threeStar,

                '2' =>
                    $twoStar,

                '1' =>
                    $oneStar,

            ],

        ]);

    }
}