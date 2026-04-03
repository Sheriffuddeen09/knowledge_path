<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use App\Mail\OrderPlacedMail;
use App\Mail\NewOrderMail;
use App\Mail\OrderNotificationMail;
use App\Models\Product;
use App\Models\Message;
use App\Models\Chat;
use Illuminate\Support\Facades\DB;
use App\Models\SavedProduct;
use App\Models\User;



class OrderController extends Controller
{
    // ✅ CREATE ORDER 

    public function create(Request $request)
{
    $request->validate([
        'email' => 'required|email',
        'items' => 'required|array|min:1',
    ]);

    DB::beginTransaction();

    try {

        $buyerId = $request->user_id;

        $uniqueItems = collect($request->items)
            ->unique('product_id')
            ->values();

       
        $productIds = $uniqueItems->pluck('product_id');

        $existingOrder = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.user_id', $buyerId)
            ->whereIn('order_items.product_id', $productIds)
            ->where('orders.created_at', '>=', now()->subMinutes(2))
            ->exists();

        if ($existingOrder) {
            return response()->json([
                'success' => false,
                'message' => 'You already placed this order recently'
            ], 409);
        }

       
        $subtotal = $uniqueItems->sum(function ($item) {
            return $item['price'] * $item['quantity'];
        });

        $delivery = $request->delivery_price ?? 0;
        $discount = $request->discount ?? 0;
        $total = $subtotal + $delivery - $discount;

        if (!$request->order_token) {
                return response()->json([
                    'success' => false,
                    'message' => 'Missing order token'
                ], 400);
            }

            // 🔥 HARD LOCK (DB LEVEL)
            $existing = Order::where('order_token', $request->order_token)->first();

            if ($existing) {
                return response()->json([
                    'success' => true,
                    'order_id' => $existing->id,
                    'message' => 'Duplicate prevented'
                ]);
            }

        $order = Order::create([
            'user_id' => $buyerId,
            'order_token' => $request->order_token,
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'email' => $request->email,
            'phone' => $request->phone,
            'address' => $request->address,
            'city' => $request->city,
            'state' => $request->state,
            'zip' => $request->zip,
            'payment_method' => $request->payment_method,
        
            'subtotal' => $subtotal,
            'delivery_price' => $delivery,
            'discount' => $discount,
            'total_price' => $total,

            'status' => 'pending',
        ]);

        $sellerMap = [];

        

        foreach ($uniqueItems as $item) {

            $product = Product::find($item['product_id']);
            if (!$product) continue;

            $sellerId = $product->user_id;

            // SAVE ORDER ITEM
            OrderItem::create([
                'order_id' => $order->id,
                'product_id' => $product->id,
                'title' => $item['title'] ?? $item['name'],
                'price' => $item['price'],
                'quantity' => $item['quantity'],
                'seller_id' => $product->user_id,
                'description' => $item['description'] ?? '',
                'total_price' => $item['price'] * $item['quantity'],
                
            ]);

            // =========================
            // 🔥 FIX CHAT (NO DUPLICATE)
            // =========================
            $userOne = min($buyerId, $sellerId);
            $userTwo = max($buyerId, $sellerId);

            $chat = Chat::firstOrCreate([
                'user_one_id' => $userOne,
                'user_two_id' => $userTwo,

                 'type' => 'private',
            ]);

            // =========================
            // 🔥 PREVENT DUPLICATE MESSAGE
            // =========================
            $alreadySent = Message::where('chat_id', $chat->id)
                ->where('message', 'LIKE', "%Order (#{$order->id})%")
                ->exists();

            if (!$alreadySent) {
                Message::create([
                    'chat_id' => $chat->id,
                    'sender_id' => $buyerId,
                    'receiver_id' => $sellerId,
                    'type' => 'text',
                    'message' => "🛒 New Order (#{$order->id}) placed.",
                ]);
            }

            $sellerMap[$sellerId] = true;
        }

        // =========================
        // 🔥 6. EMAILS
        // =========================
        Mail::to("odukoyasheriff@gmail.com")
            ->send(new OrderNotificationMail($order, 'created'));

        Mail::to($order->email)
            ->send(new OrderPlacedMail($order));

        foreach (array_keys($sellerMap) as $sellerId) {
            $seller = User::find($sellerId);

            if ($seller && filter_var($seller->email, FILTER_VALIDATE_EMAIL)) {
                Mail::to($seller->email)
                    ->send(new NewOrderMail($order));
            }
        }

        DB::commit();

        return response()->json([
            'success' => true,
            'order_id' => $order->id
        ]);

    } catch (\Exception $e) {
        DB::rollBack();

        return response()->json([
            'success' => false,
            'message' => 'Order failed',
            'error' => $e->getMessage()
        ], 500);
    }
}

public function destroy($id, Request $request)
{
    $userId = $request->user_id;

    DB::beginTransaction();

    try {
        $order = Order::with('items')->find($id);

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Order not found'
            ]);
        }

        // ✅ BUYER DELETE
        if ($order->user_id == $userId) {
            $order->buyer_deleted = true;
        }

        // ✅ SELLER DELETE (check items)
        foreach ($order->items as $item) {
            if ($item->seller_id == $userId) {
                $order->seller_deleted = true;
            }
        }

        $order->save();

        DB::commit();

        return response()->json([
            'success' => true,
            'message' => 'Removed successfully'
        ]);

    } catch (\Exception $e) {
        DB::rollBack();

        return response()->json([
            'success' => false,
            'message' => 'Delete failed'
        ]);
    }
}

public function acceptChat($chatId)
{
    $chat = Chat::findOrFail($chatId);

    if ($chat->status === 'pending') {
        $chat->status = 'accepted';
        $chat->save();
    }

    return response()->json(['success' => true]);
}


    
public function cancel($id, Request $request)
{
    DB::beginTransaction();

    try {

        $order = Order::find($id);

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Order not found'
            ]);
        }

        // 🔥 SET STATUS (THIS IS WHAT YOU WERE MISSING)
        $order->status = 'cancelled';
        $order->save();

        DB::commit();

        return response()->json([
            'success' => true,
            'message' => 'Order cancelled'
        ]);

    } catch (\Exception $e) {
        DB::rollBack();

        return response()->json([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
}


public function index(Request $request)
{
    $userId = auth()->id();

$orders = Order::with(['items.product.images'])
    ->where(function ($q) use ($userId) {

        // =========================
        // BUYER ORDERS
        // =========================
        $q->where('user_id', $userId)
          ->where(function ($q2) {
              $q2->where('buyer_deleted', false)
                 ->orWhereNull('buyer_deleted');
          });
    })

    ->orWhere(function ($q) use ($userId) {

        // =========================
        // SELLER ORDERS
        // =========================
        $q->whereHas('items', function ($q2) use ($userId) {
            $q2->where('seller_id', $userId);
        })
        ->where(function ($q3) {
            $q3->where('seller_deleted', false)
               ->orWhereNull('seller_deleted');
        });
    })

    ->orderBy('id', 'asc')
    ->get();

    // ========================= destroy
    // 🔥 CLEAN ITEMS + CHAT
    // =========================
    $orders->transform(function ($order) use ($userId) {

        $isBuyer = $order->user_id == $userId;

        // ✅ FILTER ITEMS HERE (NOT IN QUERY)
        $order->items = $order->items
            ->filter(function ($item) use ($isBuyer, $userId) {
                return $isBuyer || $item->seller_id == $userId;
            })
            ->unique('product_id')
            ->values()
            ->map(function ($item) {

                if ($item->product) {
                    $mainImage = $item->product->images
                        ->where('position', 'main')
                        ->first()
                        ?? $item->product->images->first();

                    $item->product->image = $mainImage
                        ? $mainImage->image_path
                        : null;
                }

                return $item;
            });

        // =========================
        // ✅ CHAT STATUS
        // =========================
        $order->chat_created = false;

        foreach ($order->items as $item) {

            $sellerId = $item->seller_id;

            $userOne = min($order->user_id, $sellerId);
            $userTwo = max($order->user_id, $sellerId);

            $chatExists = Chat::where('user_one_id', $userOne)
                ->where('user_two_id', $userTwo)
                ->exists();

            if ($chatExists) {
                $order->chat_created = true;
                break;
            }
        }

        return $order;
    });

    return response()->json([
        'success' => true,
        'orders' => $orders
    ]);
}

public function saveDraft(Request $request)
{
    try {
        $userId = $request->user_id;

        // 🔥 Extract product_id safely
        $productId = $request->items[0]['product_id'] ?? null;

        if (!$productId) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid product'
            ], 422);
        }

        // 🔥 CHECK DUPLICATE
        $exists = DB::table('saved_products')
            ->where('user_id', $userId)
            ->where('status', 'draft')
            ->get()
            ->contains(function ($item) use ($productId) {
                $data = json_decode($item->data, true);
                return isset($data['items'][0]['product_id']) &&
                       $data['items'][0]['product_id'] == $productId;
            });

        if ($exists) {
            return response()->json([
                'success' => false,
                'message' => 'Product already saved'
            ], 409);
        }

        // ✅ SAVE
        DB::table('saved_products')->insert([
            'user_id' => $userId,
            'data' => json_encode($request->all()),
            'status' => 'draft',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Saved successfully'
        ]);

    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Save failed',
            'error' => $e->getMessage()
        ], 500);
    }
}

public function getDrafts($userId)
{
    $drafts = DB::table('saved_products')
        ->where('user_id', $userId)
        ->where('status', 'draft')
        ->latest()
        ->get();

    // 🔥 DECODE JSON DATA
    $drafts->transform(function ($item) {
        $item->data = json_decode($item->data, true);
        return $item;
    });

    return response()->json($drafts);
}

public function deleteDraft($id)
{
    $draft = SavedProduct::where('user_id', auth()->id())
        ->findOrFail($id);

    $draft->delete();

    return response()->json([
        'success' => true
    ]);
}


    public function createChat(Request $request)
{
    $request->validate([
        'seller_id' => 'required|exists:users,id',
        'order_id' => 'required|exists:orders,id',
    ]);

    $buyerId = auth()->id();
    $sellerId = $request->seller_id;

    // normalize chat (IMPORTANT)
    $userOne = min($buyerId, $sellerId);
    $userTwo = max($buyerId, $sellerId);

    $chat = Chat::firstOrCreate([
        'user_one_id' => $userOne,
        'user_two_id' => $userTwo,
        'type' => 'marketplace',
    ]);

    // optional auto message
    Message::create([
        'chat_id' => $chat->id,
        'sender_id' => $buyerId,
        'receiver_id' => $sellerId,
        'type' => 'text',
        'message' => "Hi, I want to discuss my order #{$request->order_id}",
    ]);

    return response()->json([
        'success' => true,
        'chat_id' => $chat->id
    ]);
}

public function unreadCount($orderId)
{
    $userId = auth()->id();

    $count = Message::where('order_id', $orderId)
        ->where('receiver_id', $userId)
        ->where('is_read', false)
        ->count();

    return response()->json([
        'unread' => $count
    ]);
}
}