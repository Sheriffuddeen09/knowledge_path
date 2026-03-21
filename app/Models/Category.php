<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'parent_id' // ✅ ADD THIS
    ];

    // 🔹 Parent category
    public function parent()
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    // 🔹 Child categories
    public function children()
    {
        return $this->hasMany(Category::class, 'parent_id');
    }

    // 🔹 Products (IMPORTANT for your eCommerce)
    public function products()
    {
        return $this->hasMany(Product::class);
    }

    // 🔹 (Optional) Keep your videos
    public function videos()
    {
        return $this->hasMany(Video::class);
    }
}