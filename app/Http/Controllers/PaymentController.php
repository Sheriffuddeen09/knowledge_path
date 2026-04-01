<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Transaction;
use Illuminate\Support\Facades\Mail;
use App\Mail\OrderPlacedMail;
use App\Mail\NewOrderMail;
use App\Models\Product;
use App\Models\User;

class PaymentController extends Controller
{
    public function createPaymentIntent(Request $request)
{
    \Stripe\Stripe::setApiKey(env('STRIPE_SECRET'));

    $intent = \Stripe\PaymentIntent::create([
        'amount' => $request->amount * 100,
        'currency' => 'usd',
        'payment_method_types' => ['card'],
    ]);

    return response()->json([
        'clientSecret' => $intent->client_secret
    ]);
}



public function stripeWebhook(Request $request)
{
    \Stripe\Stripe::setApiKey(env('STRIPE_SECRET'));

    $payload = $request->getContent();
    $sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

    try {
        $event = \Stripe\Webhook::constructEvent(
            $payload,
            $sig_header,
            env('STRIPE_WEBHOOK_SECRET')
        );
    } catch (\Exception $e) {
        return response('Invalid payload', 400);
    }

    // ✅ PAYMENT SUCCESS
    if ($event->type === 'payment_intent.succeeded') {

        $paymentIntent = $event->data->object;
        $reference = $paymentIntent->id;

        $transaction = Transaction::where('reference', $reference)->first();

        if ($transaction) {

            // ✅ prevent duplicate execution
            if ($transaction->status === 'success') {
                return response('Already processed', 200);
            }

            // ✅ update transaction
            $transaction->update([
                'status' => 'success'
            ]);

            // ✅ update order
            $order = $transaction->order;
            $order->update([
                'status' => 'paid'
            ]);

            // 🔥 SEND EMAIL TO BUYER
            Mail::to($order->email)->send(new OrderPlacedMail($order));

            // 🔥 SEND EMAIL TO SELLERS
            foreach ($order->items as $item) {

                $product = Product::find($item->product_id);

                if ($product && $product->user_id) {

                    $seller = User::find($product->user_id);

                    if ($seller) {
                        Mail::to($seller->email)->send(new NewOrderMail($order));
                    }
                }
            }
        }
    }

    return response('Webhook handled', 200);
}


public function updateRef(Request $request)
{
    $transaction = \App\Models\Transaction::where('order_id', $request->order_id)->first();

    if ($transaction) {
        $transaction->update([
            'reference' => $request->reference
        ]);
    }

    return response()->json(['success' => true]);
}


public function verifyPaypal(Request $request)
{
    $reference = $request->reference;

    $transaction = Transaction::where('reference', $reference)->first();

    if ($transaction && $transaction->status !== 'success') {

        $transaction->update(['status' => 'success']);

        $order = $transaction->order;
        $order->update(['status' => 'paid']);

        // 🔥 EMAIL BUYER
        Mail::to($order->email)->send(new OrderPlacedMail($order));

        // 🔥 EMAIL SELLERS
        foreach ($order->items as $item) {
            $product = Product::find($item->product_id);

            if ($product && $product->user_id) {
                $seller = User::find($product->user_id);

                if ($seller) {
                    Mail::to($seller->email)->send(new NewOrderMail($order));
                }
            }
        }
    }

    return response()->json(['success' => true]);
}


public function paystackWebhook(Request $request)
{
    // ✅ GET RAW PAYLOAD
    $payload = file_get_contents("php://input");
    $event = json_decode($payload);

    // ✅ VERIFY PAYSTACK SIGNATURE (IMPORTANT)
    $signature = $_SERVER['HTTP_X_PAYSTACK_SIGNATURE'] ?? '';

    if ($signature !== hash_hmac('sha512', $payload, env('PAYSTACK_SECRET'))) {
        return response('Invalid signature', 400);
    }

    // ✅ YOUR CODE GOES HERE 👇
    if ($event->event === "charge.success") {

        $reference = $event->data->reference;

        $transaction = \App\Models\Transaction::where('reference', $reference)->first();

        if ($transaction && $transaction->status !== 'success') {

            $transaction->update(['status' => 'success']);

            $order = $transaction->order;
            $order->update(['status' => 'paid']);

            // 🔥 EMAIL BUYER
            \Mail::to($order->email)->send(new \App\Mail\OrderPlacedMail($order));

            // 🔥 EMAIL SELLERS
            foreach ($order->items as $item) {
                $product = \App\Models\Product::find($item->product_id);

                if ($product && $product->user_id) {
                    $seller = \App\Models\User::find($product->user_id);

                    if ($seller) {
                        \Mail::to($seller->email)->send(new \App\Mail\NewOrderMail($order));
                    }
                }
            }
        }
    }

    return response('Webhook handled', 200);
}

}