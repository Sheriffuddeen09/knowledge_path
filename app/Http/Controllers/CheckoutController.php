<?php

use Illuminate\Http\Request;
use App\Models\Order;
use Illuminate\Support\Facades\Auth;

class CheckoutController extends Controller
{
   use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

public function store(Request $request)
{
    $request->validate([
        'first_name' => 'required|string',
        'last_name' => 'required|string',
        'phone' => 'required|string',
        'email' => 'required|string',
        'address' => 'required|string',
        'addressSecond' => 'required|string',
        'payment_method' => 'required|string',
        'cart' => 'required|array',
    ]);

    $user = Auth::user();

    DB::beginTransaction();

    try {
        $subtotal = 0;
        $delivery = 0;
        $discount = 0;

        foreach ($request->cart as $item) {
            $subtotal += $item['product']['price'] * $item['quantity'];
            $delivery += $item['product']['delivery_price'] ?? 0;
            $discount += $item['product']['discount'] ?? 0;
        }

        $total = $subtotal + $delivery - $discount;

        // ✅ CREATE ORDER

        $order = Order::create([
            'user_id' => $user->id,
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'email' => $request->email,
            'phone' => $request->phone,
            'address' => $request->address,
            'addressSecond' => $request->addressSecond,
            'city' => $request->city,
            'state' => $request->state,
            'zip' => $request->zip,
            'payment_method' => $request->payment_method,
            'subtotal' => $subtotal,
            'delivery_price' => $delivery,
            'discount' => $discount,
            'total_price' => $total,
        ]);

        // ✅ CREATE ITEMS
          $sellerId = $item['product']['user_id'];
        foreach ($request->cart as $item) {
            OrderItem::create([
                'order_id' => $order->id,
                'seller_id' => $sellerId,
                'product_id' => $item['product']['id'],
                'title' => $item['product']['title'],
                'image' => $item['product']['images'][0]['image_path'] ?? null,
                'quantity' => $item['quantity'],
                'price' => $item['product']['price'],
                'delivery_price' => $item['product']['delivery_price'] ?? 0,
                'discount' => $item['product']['discount'] ?? 0,
                'total_price' => $item['product']['price'] * $item['quantity'],
                'delivery_method' => $item['product']['delivery_method'],
            ]);
        }

        // ✅ SAVE ADDRESS TO USER (AUTO-FILL NEXT TIME)
        $user->update([
            'address' => $request->address,
            'addressSecond' => $request->addressSecond,
            'city' => $request->city,
            'state' => $request->state,
            'zip' => $request->zip,
        ]);

        DB::commit();

        return response()->json([
            'message' => 'Order placed successfully',
            'order' => $order->load('items')
        ]);

    } catch (\Exception $e) {
        DB::rollback();
        return response()->json(['error' => $e->getMessage()], 500);
    }
}
}