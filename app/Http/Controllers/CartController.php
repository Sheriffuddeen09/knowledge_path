<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Cart;
use Illuminate\Support\Facades\Auth;

class CartController extends Controller
{

    public function getCart()
    {
        $user = Auth::user();

        $cart = Cart::with('product.images') // include images
            ->where('user_id', $user->id)
            ->get();

        return response()->json([
            'cart' => $cart
        ]);
    }

    public function addToCart(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'quantity' => 'required|integer|min:1',
        ]);

        $user = Auth::user();

        // Check if product already exists in cart
        $cartItem = Cart::where('user_id', $user->id)
                        ->where('product_id', $request->product_id)
                        ->first();

        if ($cartItem) {
            $cartItem->quantity += $request->quantity;
            $cartItem->save();
        } else {
            $cartItem = Cart::create([
                'user_id' => $user->id,
                'product_id' => $request->product_id,
                'quantity' => $request->quantity,
            ]);
        }

        // Return the updated cart
        $cart = Cart::with('product')->where('user_id', $user->id)->get();

        return response()->json(['cart' => $cart]);
    }

    public function updateCart(Request $request, $id)
        {
            $request->validate([
                'quantity' => 'required|integer|min:1',
            ]);

            $user = Auth::user();

            $cartItem = Cart::where('id', $id)->where('user_id', $user->id)->firstOrFail();

            $cartItem->quantity = $request->quantity;
            $cartItem->save();

            // Return updated cart
            $cart = Cart::with('product')->where('user_id', $user->id)->get();
            return response()->json(['cart' => $cart]);
        }


        public function deleteCart($id)
            {
                $user = Auth::user();

                $cartItem = Cart::where('id', $id)->where('user_id', $user->id)->firstOrFail();

                $cartItem->delete();

                // Return updated cart
                $cart = Cart::with('product')->where('user_id', $user->id)->get();
                return response()->json(['cart' => $cart]);
            }

}