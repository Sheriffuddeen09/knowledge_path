<?php

namespace App\Http\Controllers;

use App\Models\Cart;
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
    public function create(Request $request)
{
    $request->validate([
        'email' => 'required|email',
        'items' => 'required|array|min:1',
        'order_token' => 'required|string',
    ]);

    DB::beginTransaction();

    try {

       
        $buyerId = auth()->id();

        if (!$buyerId) {

            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated'
            ], 401);
        }


        $uniqueItems = collect($request->items)
            ->unique('product_id')
            ->values();


        if ($uniqueItems->isEmpty()) {

            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'No products found in order.'
            ], 422);
        }


    
        $productIds = $uniqueItems
            ->pluck('product_id')
            ->filter()
            ->values();


        $existingOrder = DB::table('order_items')
            ->join(
                'orders',
                'orders.id',
                '=',
                'order_items.order_id'
            )
            ->where('orders.user_id', $buyerId)
            ->whereIn(
                'order_items.product_id',
                $productIds
            )
            ->where(
                'orders.created_at',
                '>=',
                now()->subMinutes(2)
            )
            ->exists();


        if ($existingOrder) {

            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'You already placed this order recently'
            ], 409);
        }


        $existing = Order::where(
            'order_token',
            $request->order_token
        )->first();


        if ($existing) {

            DB::rollBack();

            return response()->json([
                'success' => true,
                'order_id' => $existing->id,
                'message' => 'Duplicate prevented'
            ]);
        }


        $subtotal = $uniqueItems->sum(function ($item) {

            $price = (float) ($item['price'] ?? 0);

            $quantity = (int) ($item['quantity'] ?? 1);

            return $price * $quantity;
        });


        $delivery = (float) (
            $request->delivery_price ?? 0
        );


        $discount = (float) (
            $request->discount ?? 0
        );


        
        $total = $subtotal
            + $delivery
            - $discount;


        // Prevent negative total
        $total = max($total, 0);


        $order = Order::create([

            'user_id' => $buyerId,

            'order_token' =>
                $request->order_token,

            'first_name' =>
                $request->first_name,

            'last_name' =>
                $request->last_name,

            'email' =>
                $request->email,

            'phone' =>
                $request->phone,

            'address' =>
                $request->address,

            'city' =>
                $request->city,

            'state' =>
                $request->state,

            'zip' =>
                $request->zip,

            'payment_method' =>
                $request->payment_method,

            'subtotal' =>
                $subtotal,

            'delivery_price' =>
                $delivery,

            'discount' =>
                $discount,

            // IMPORTANT
            // Use calculated $total
            'total_price' =>
                $total,

            'status' =>
                'pending',

            'seen' =>
                false,
        ]);


        $sellerMap = [];


        foreach ($uniqueItems as $item) {

            $product = Product::find(
                $item['product_id']
            );


            if (!$product) {
                continue;
            }


            $sellerId = $product->user_id;


            $itemPrice = (float) (
                $item['price']
                ?? $product->price
                ?? 0
            );


            $itemQuantity = (int) (
                $item['quantity']
                ?? 1
            );


            $itemDiscount = (float) (
                $item['discount']
                ?? 0
            );


            $itemTotal =
                ($itemPrice * $itemQuantity)
                - $itemDiscount;


            $itemTotal = max(
                $itemTotal,
                0
            );

            $orderItem = OrderItem::create([
                'order_id' => $order->id,
                'product_id' => $product->id,

                'title' => $item['title']
                    ?? $item['name']
                    ?? $product->name,

                'price' => (float) ($item['price'] ?? $product->price),

                'quantity' => (int) ($item['quantity'] ?? 1),

                'seller_id' => $product->user_id,

                'description' =>
                    $item['description']
                    ?? $product->description
                    ?? '',

                'currency' =>
                    $product->currency ?? 'USD',

                'discount' =>
                    (float) ($item['discount'] ?? $product->discount ?? 0),

                'total_price' =>
                    (
                        (float) ($item['price'] ?? $product->price ?? 0)
                        *
                        (int) ($item['quantity'] ?? 1)
                    )
                    -
                    (float) ($item['discount'] ?? $product->discount ?? 0),
            ]);

            $sellerMap[$sellerId] = true;

            $userOne = min(
                $buyerId,
                $sellerId
            );

            $userTwo = max(
                $buyerId,
                $sellerId
            );


            $chat = Chat::firstOrCreate([

                'user_one_id' =>
                    $userOne,

                'user_two_id' =>
                    $userTwo,

                'type' =>
                    'private',

            ]);


            $alreadySent = Message::where(
                'chat_id',
                $chat->id
            )
            ->where(
                'message',
                'LIKE',
                "%Order (#{$order->id})%"
            )
            ->exists();


            if (!$alreadySent) {

                Message::create([

                    'chat_id' =>
                        $chat->id,

                    'sender_id' =>
                        $buyerId,

                    'receiver_id' =>
                        $sellerId,

                    'type' =>
                        'text',

                    'message' =>
                        "🛒 New Order (#{$order->id}) placed.",

                ]);
            }
        }

        if ($order->items()->count() === 0) {

            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Order could not be created because no valid products were found.'
            ], 422);
        }


        Cart::where(
            'user_id',
            $buyerId
        )->delete();


        Mail::to(
            "odukoyasheriff@gmail.com"
        )->send(
            new OrderNotificationMail(
                $order,
                'created'
            )
        );

        Mail::to(
            $order->email
        )->send(
            new OrderPlacedMail(
                $order
            )
        );

        foreach (
            array_keys($sellerMap)
            as $sellerId
        ) {

            $seller = User::find(
                $sellerId
            );


            if (
                $seller &&
                filter_var(
                    $seller->email,
                    FILTER_VALIDATE_EMAIL
                )
            ) {

                Mail::to(
                    $seller->email
                )->send(
                    new NewOrderMail(
                        $order
                    )
                );
            }
        }


        DB::commit();


        return response()->json([

            'success' =>
                true,

            'order_id' =>
                $order->id,

            'message' =>
                'Order placed successfully and cart cleared.',

        ]);


    } catch (\Throwable $e) {

        DB::rollBack();



        return response()->json([

            'success' =>
                false,

            'message' =>
                'Order failed',

            'error' =>
                $e->getMessage(),

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
    ->where(function ($query) use ($userId) {

        // =========================
        // BUYER ORDERS
        // =========================
        $query->where(function ($q) use ($userId) {
            $q->where('user_id', $userId)
              ->where(function ($q2) {
                  $q2->where('buyer_deleted', false)
                     ->orWhereNull('buyer_deleted');
              });
        })

        // =========================
        // SELLER ORDERS
        // =========================
        ->orWhere(function ($q) use ($userId) {
            $q->whereHas('items', function ($q2) use ($userId) {
                $q2->where('seller_id', $userId);
            })
            ->where(function ($q3) {
                $q3->where('seller_deleted', false)
                   ->orWhereNull('seller_deleted');
            });
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
    'orders' => $orders,
    'count' => $orders->where('seen', false)->count()
]);
}

public function count(Request $request)
{
    $userId = $request->user_id;

    $count = Order::where(function ($q) use ($userId) {

    $q->where('user_id', $userId)
      ->orWhereIn('id', function ($sub) use ($userId) {
          $sub->select('order_id')
              ->from('order_items')
              ->where('seller_id', $userId);
      });

    })
    ->where('seen', 0)
    ->count();

    return response()->json([
        'success' => true,
        'count' => $count
    ]);
}


public function markAsSeen(Request $request)
{
    $userId = $request->user_id;

    Order::where(function ($q) use ($userId) {

        $q->where('user_id', $userId)
          ->orWhereHas('items', function ($q2) use ($userId) {
              $q2->where('seller_id', $userId);
          });

    })
    ->update(['seen' => true]);

    return response()->json([
        'success' => true,
        'message' => 'Orders marked as seen'
    ]);
}

public function saveDraft(Request $request)
{
    try {

        $userId = $request->user()->id;

        // =====================================================
        // GET ITEMS
        // =====================================================

        $items = collect(
            $request->input('items', [])
        );


        if ($items->isEmpty()) {

            return response()->json([
                'success' => false,
                'message' => 'No products provided'
            ], 422);
        }


        // =====================================================
        // PRODUCT IDS
        // =====================================================

        $productIds = $items
            ->pluck('product_id')
            ->filter()
            ->unique()
            ->values();


        if ($productIds->isEmpty()) {

            return response()->json([
                'success' => false,
                'message' => 'Invalid product'
            ], 422);
        }


        // =====================================================
        // GET PRODUCTS
        // =====================================================

        $products = Product::whereIn(
            'id',
            $productIds
        )
        ->get()
        ->keyBy('id');


        // =====================================================
        // CHECK THAT ALL PRODUCTS EXIST
        // =====================================================

        foreach ($productIds as $productId) {

            if (!$products->has($productId)) {

                return response()->json([
                    'success' => false,
                    'message' => "Product {$productId} was not found."
                ], 404);
            }
        }


        // =====================================================
        // CHECK DUPLICATE SAVED PRODUCTS
        // =====================================================

        foreach ($productIds as $productId) {

            $exists = DB::table('saved_products')
                ->where('user_id', $userId)
                ->where('status', 'draft')
                ->get()
                ->contains(function ($saved) use ($productId) {

                    $data = json_decode(
                        $saved->data,
                        true
                    );


                    return collect(
                        $data['items'] ?? []
                    )->contains(function ($item) use ($productId) {

                        return (int) (
                            $item['product_id'] ?? 0
                        ) === (int) $productId;

                    });
                });


            if ($exists) {

                return response()->json([
                    'success' => false,
                    'message' => 'One or more products are already saved.'
                ], 409);
            }
        }


        // =====================================================
        // BUILD SAVED ITEMS
        // =====================================================

        $items = $items->map(function ($item) use ($products) {

            $product = $products->get(
                $item['product_id'] ?? null
            );


            if (!$product) {
                return null;
            }


            // =================================================
            // PRODUCT PRICE
            // =================================================

            $price = (float) (
                $product->price ?? 0
            );


            // =================================================
            // PRODUCT DISCOUNT
            // =================================================

            $discount = (float) (
                $product->discount ?? 0
            );


            // =================================================
            // QUANTITY
            // =================================================

            $quantity = (int) (
                $item['quantity'] ?? 1
            );


            // =================================================
            // CURRENCY
            // =================================================

            $currency =
                $product->currency
                ?? 'USD';


            // =================================================
            // IMAGE
            // =================================================

            $image =
                $item['image']
                ?? $product->image
                ?? null;


            // =================================================
            // RETURN CLEAN ITEM
            // =================================================

            return [

                'product_id' =>
                    $product->id,

                'name' =>
                    $product->name
                    ?? $item['name']
                    ?? 'Unnamed Product',

                'price' =>
                    $price,

                'discount' =>
                    $discount,

                'quantity' =>
                    $quantity,

                'currency' =>
                    $currency,

                'seller_id' =>
                    $product->user_id,

                'image' =>
                    $image,

                'description' =>
                    $product->description
                    ?? $item['description']
                    ?? null,
            ];

        })
        ->filter()
        ->values();


        // =====================================================
        // CALCULATE TOTALS
        // =====================================================

        $subtotal = $items->sum(function ($item) {

            return
                (float) $item['price']
                *
                (int) $item['quantity'];
        });


        $discount = $items->sum(function ($item) {

            return (float) (
                $item['discount'] ?? 0
            );
        });


        $delivery = (float) (
            $request->delivery_price ?? 0
        );


        $total = $subtotal
            + $delivery
            - $discount;


        $total = max(
            $total,
            0
        );


        // =====================================================
        // BUILD DRAFT DATA
        // =====================================================

        $draftData = $request->except(
            'user_id',
            'items'
        );


        $draftData['items'] =
            $items->toArray();


        $draftData['subtotal'] =
            $subtotal;


        $draftData['discount'] =
            $discount;


        $draftData['delivery_price'] =
            $delivery;


        $draftData['total_price'] =
            $total;


        $draftData['status'] =
            'draft';


        // =====================================================
        // SAVE DRAFT
        // =====================================================

        DB::table('saved_products')->insert([

            'user_id' =>
                $userId,

            'data' =>
                json_encode(
                    $draftData
                ),

            'status' =>
                'draft',

            'created_at' =>
                now(),

            'updated_at' =>
                now(),

        ]);


        // =====================================================
        // RESPONSE
        // =====================================================

        return response()->json([

            'success' =>
                true,

            'message' =>
                'Saved successfully',

            'subtotal' =>
                $subtotal,

            'discount' =>
                $discount,

            'total_price' =>
                $total,

        ]);


    } catch (\Exception $e) {

        return response()->json([

            'success' =>
                false,

            'message' =>
                'Save failed',

            'error' =>
                $e->getMessage()

        ], 500);
    }
}

public function getDrafts(Request $request)
{
    try {

        $user = $request->user();

       

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated'
            ], 401);
        }

        $drafts = DB::table('saved_products')
            ->where('user_id', $user->id)
            ->where('status', 'draft')
            ->latest()
            ->get();

       

        $drafts->transform(function ($item) {

            $item->data = json_decode($item->data, true);

            return $item;
        });

        return response()->json($drafts);

    } catch (\Exception $e) {

       

        return response()->json([
            'success' => false,
            'message' => 'Failed to load drafts',
            'error' => $e->getMessage()
        ], 500);
    }
}

public function deleteDraft($id)
{
    $draft = SavedProduct::where('user_id', auth()->id())
        ->where('id', $id)
        ->first();

    if (!$draft) {
        return response()->json([
            'success' => false,
            'message' => 'Draft not found'
        ], 404);
    }

    $draft->delete();

    return response()->json([
        'success' => true,
        'message' => 'Draft deleted successfully'
    ]);
}


    public function createChat(Request $request)
{
    $request->validate([
        'user_id' => 'required|exists:users,id',
        'order_id' => 'required|exists:orders,id',
    ]);

    $currentUserId = (int) auth()->id();
    $otherUserId = (int) $request->user_id;
    $orderId = (int) $request->order_id;

    // Prevent chatting with yourself
    if ($currentUserId === $otherUserId) {
        return response()->json([
            'success' => false,
            'message' => 'You cannot chat with yourself'
        ], 400);
    }

    $order = Order::with('items')->findOrFail($orderId);

    $isBuyer = (int) $order->user_id === $currentUserId;

    $isSeller = $order->items->contains(function ($item) use ($currentUserId) {
        return (int) $item->seller_id === $currentUserId;
    });

    if (!$isBuyer && !$isSeller) {
        return response()->json([
            'success' => false,
            'message' => 'You are not authorized to chat for this order.'
        ], 403);
    }


    if ($isBuyer) {


        $sellerExists = $order->items->contains(function ($item) use ($otherUserId) {
            return (int) $item->seller_id === $otherUserId;
        });

        if (!$sellerExists) {
            return response()->json([
                'success' => false,
                'message' => 'This seller is not part of this order.'
            ], 403);
        }
    }

    if ($isSeller) {

        if ((int) $order->user_id !== $otherUserId) {
            return response()->json([
                'success' => false,
                'message' => 'This buyer is not associated with this order.'
            ], 403);
        }
    }

    $userOne = min(
        $currentUserId,
        $otherUserId
    );

    $userTwo = max(
        $currentUserId,
        $otherUserId
    );

    $chat = Chat::where('user_one_id', $userOne)
        ->where('user_two_id', $userTwo)
        ->first();



    if (!$chat) {

        $chat = Chat::create([
            'user_one_id' => $userOne,
            'user_two_id' => $userTwo,
            'order_id' => $orderId,
            'type' => 'marketplace',
        ]);
    }


    return response()->json([
        'success' => true,
        'chat' => $chat,
        'chat_id' => $chat->id
    ]);
}
}