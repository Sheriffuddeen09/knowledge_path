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

<h2>You have a new order 🎉</h2>

<p>
    Order ID: {{ $order->id }}
</p>

<p>
    Customer:
    {{ $order->first_name }}
    {{ $order->last_name }}
</p>

<h3>Order Items</h3>

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
            Price:
            {{ $symbol }}{{ number_format($price, 2) }}
            × {{ $quantity }}
        </p>

        @if($discount > 0)
            <p>
                Discount:
                -{{ $symbol }}{{ number_format($discount, 2) }}
            </p>
        @endif

        <p>
            Subtotal:
            <strong>
                {{ $symbol }}{{ number_format($subtotal, 2) }}
            </strong>
        </p>

        <p>
            USD Equivalent:
            <strong>
                ${{ number_format($usdSubtotal, 2) }}
            </strong>
        </p>

    </div>

@endforeach

<hr>

<h3>
    Total in USD:
    ${{ number_format($order->total_usd, 2) }}
</h3>