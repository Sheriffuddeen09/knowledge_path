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

    // POST /api/products/{id}/reviews
    public function store(Request $request, $productId)
    {
        $request->validate([
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'required|string|max:1000',
        ]);

        $review = Review::create([
            'product_id' => $productId,
            'user_id' => Auth::id(), // assuming user is logged in
            'rating' => $request->rating,
            'comment' => $request->comment,
        ]);

        $review->load('user'); // include user data in response

        return response()->json($review, 201);
    }
}