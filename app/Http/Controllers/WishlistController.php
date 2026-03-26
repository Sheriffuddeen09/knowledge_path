<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Wishlist;
use App\Models\Cart;
use Illuminate\Support\Facades\Auth;

class WishlistController extends Controller
{
    // Fetch Wishlist
    
    public function index()
    {
        $wishlist = Wishlist::with('product.images')
        ->where('user_id', Auth::id())
        ->get();

        return response()->json([
            'wishlist' => $wishlist
        ]);
    }

    // Add to Wishlist
   
    public function store(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'quantity' => 'required|integer|min:1',
        ]);

        $user = Auth::user();

        // Check if product already exists in wishlist
        $wishlist = Wishlist::where('user_id', $user->id)
                        ->where('product_id', $request->product_id)
                        ->first();

        if ($wishlist) {
            $wishlist->quantity += $request->quantity;
            $wishlist->save();
        } else {
            $wishlist = Wishlist::create([
                'user_id' => $user->id,
                'product_id' => $request->product_id,
                'quantity' => $request->quantity,
            ]);
        }

        // Return the updated cart
        $wishlist = Wishlist::with('product')->where('user_id', $user->id)->get();

        return response()->json(['wishlist' => $wishlist]);
    }
    // Update quantity
    public function update(Request $request, $id)
    {
        $request->validate([
            'quantity' => 'required|integer|min:1',
        ]);

        $item = Wishlist::where('user_id', Auth::id())->findOrFail($id);
        $item->quantity = $request->quantity;
        $item->save();

        return response()->json(['wishlist' => Wishlist::with('product')->where('user_id', Auth::id())->get()]);
    }

    // Remove item
    public function destroy($id)
    {
        $item = Wishlist::where('user_id', Auth::id())->findOrFail($id);
        $item->delete();

        return response()->json(['wishlist' => Wishlist::with('product')->where('user_id', Auth::id())->get()]);
    }

    // Move to Cart
    public function moveToCart($id)
    {
        $item = Wishlist::where('user_id', Auth::id())->with('product')->findOrFail($id);

        // Add to cart
        Cart::updateOrCreate(
            ['user_id' => Auth::id(), 'product_id' => $item->product_id],
            ['quantity' => $item->quantity]
        );

        // Remove from wishlist
        $item->delete();

        return response()->json(['wishlist' => Wishlist::with('product')->where('user_id', Auth::id())->get()]);
    }
}