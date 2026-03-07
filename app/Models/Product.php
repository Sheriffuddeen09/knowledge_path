<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{

     protected $fillable = [
        'title',
        'author',
        'description',
        'price',
        'category_id',
        'stock',
        'color',
        'size',
        'weight',
        'front_image',
        'back_image',
        'side_image',
        'pdf_file',
        'is_digital',
        'discount',
        'charge'
    ];


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


}