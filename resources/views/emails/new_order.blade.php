<h2>You have a new order 🎉</h2>

<p>Order ID: {{ $order->id }}</p>
<p>Customer: {{ $order->first_name }} {{ $order->last_name }}</p>
<p>Total: ₦{{ $order->total_price }}</p>