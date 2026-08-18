@php
    $rates = [
        'USD' => 1,
        'NGN' => 0.000735527,
        'EUR' => 1.09,
        'GBP' => 1.27,
    ];

    $symbols = [
        'USD' => '$',
        'NGN' => '₦',
        'EUR' => '€',
        'GBP' => '£',
    ];
@endphp

<h2>Thank you for your order! 🎉</h2>

<p>
    Order ID: <strong>#{{ $order->id }}</strong>
</p>

<h3>Order Summary</h3>

@foreach($order->items as $item)

    @php
        $currency = strtoupper(
            $item->product?->currency ?? $item->currency ?? 'USD'
        );

        $symbol = $symbols[$currency] ?? $currency;

        $rate = $rates[$currency] ?? 1;

        $price = (float) $item->price;
        $quantity = (int) ($item->quantity ?? 1);
        $discount = (float) ($item->discount ?? 0);

        $subtotal = ($price * $quantity) - $discount;

        $usdSubtotal = $subtotal * $rate;
    @endphp

    <div style="margin-bottom: 15px;">

        <p>
            <strong>{{ $item->title }}</strong>
        </p>

        <p>
            {{ $symbol }}{{ number_format($price, 2) }}
            × {{ $quantity }}
        </p>

        @if($discount > 0)
            <p style="color: red;">
                Discount:
                -{{ $symbol }}{{ number_format($discount, 2) }}
            </p>
        @endif

        <p>
            Subtotal:
            {{ $symbol }}{{ number_format($subtotal, 2) }}
        </p>

        <p>
            USD:
            ${{ number_format($usdSubtotal, 2) }}
        </p>

    </div>

@endforeach

<hr>

<p>
    <strong>
        Total:
        ${{ number_format($order->total_usd, 2) }}
    </strong>
</p>

<p>
    We will process your order shortly.
</p>