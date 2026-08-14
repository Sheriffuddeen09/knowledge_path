<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{

     protected $fillable = [
        'user_id', 'title', 'author', 'description', 'price', 'discount',
        'charges', 'currency', 'stock', 'brand_name', 'company_type',
        'company_available', 'location', 'delivery_method', 'delivery_time',
        'delivery_price', 'category_id', 'front_image', 'back_image', 'side_image',
        'pdf_file', 'is_digital', 'sale_type', 'downloadable', 'key_features',
        'specifications', 'total_price', 'parent_id', 'new_subcategory',
        'visibility', 'visibility_badges', 'visibility_unlocked', 'visibility_unlocked_at',
    ];


    protected $casts = [

    'visibility_unlocked' => 'boolean',

    'visibility_unlocked_at' => 'datetime',

    'visibility_badges' => 'integer',

    'key_features' => 'array',
    
    'specifications' => 'array',
    ];

    public function visibility(): HasOne
    {
        return $this->hasOne(
            ProductVisibility::class
        );
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function images()
    {
        return $this->hasMany(ProductImage::class);
    }

    public function cartItems()
    {
        return $this->hasMany(Cart::class);
    }

    public function orderItems()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function reviews()
    {
        return $this->hasMany(Review::class);
    }

    public function wishlists()
    {
        return $this->hasMany(Wishlist::class);
    }

    public function libraries()
    {
        return $this->hasMany(Library::class);
    }

    public function specification()
    {
        return $this->hasOne(ProductSpecification::class);
    }

    public function seller()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

}