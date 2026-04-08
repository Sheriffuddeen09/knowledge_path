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