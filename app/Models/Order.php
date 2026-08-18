<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $fillable = [
        'user_id',
        'first_name',
        'last_name',
        'email',
        'phone',
        'address',
        'city',
        'state',
        'zip',
        'payment_method',
        'subtotal',
        'delivery_price',
        'discount',
        'total_price',
        'status',
        
    ];

    protected $casts = [
    'seen' => 'boolean'
    ];

    // 🔗 RELATION
    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }


    public function getTotalUsdAttribute()
    {
        $rates = [
            'USD' => 1,
            'NGN' => 0.000735527,
            'EUR' => 1.09,
            'GBP' => 1.27,
        ];

        return $this->items->sum(function ($item) use ($rates) {

            $currency = strtoupper(
                $item->product?->currency ?? $item->currency ?? 'USD'
            );

            $price = (float) $item->price;
            $quantity = (int) ($item->quantity ?? 1);
            $discount = (float) ($item->discount ?? 0);

            $subtotal = ($price * $quantity) - $discount;

            $rate = $rates[$currency] ?? 1;

            return $subtotal * $rate;
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function transaction()
    {
        return $this->hasOne(Transaction::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    
}