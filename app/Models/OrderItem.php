<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
    protected $fillable = [
        'order_id',
        'product_id',
        'title',
        'image',
        'quantity',
        'price',
        'delivery_price',
        'discount',
        'total_price',
        'delivery_method'
    ];

    // 🔗 RELATION
    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}