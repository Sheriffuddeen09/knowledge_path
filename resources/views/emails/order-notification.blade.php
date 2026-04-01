<h2>New Order Alert 🚨</h2>

<p>Order ID: {{ $order->id }}</p>
<p>Total: ₦{{ $order->total_price }}</p>

@if($type == 'paid')
    <p style="color:green;">Payment has been CONFIRMED ✅</p>
@else
    <p style="color:orange;">Order placed but not paid yet ⚠️</p>
@endif