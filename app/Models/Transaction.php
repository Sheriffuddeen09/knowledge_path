<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    protected $fillable = [
        'order_id',
        'reference',
        'payment_method',
        'amount',
        'status',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
    ];

    // ✅ RELATIONSHIP
    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}