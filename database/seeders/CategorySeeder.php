<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Category;
use Illuminate\Support\Str;

class CategorySeeder extends Seeder
{
    public function run()
    {
        $categories = [
            "Islamic Content" => ["Books"],
            "Clothes" => ["T-shirt", "Trouser"],
            "Accessory" => ["Shoes", "Watch"],
            "Electronic" => ["TV", "Laptop"],
            "House Accessory" => ["Furniture", "Kitchen Items"],
            "Others" => []
        ];

        foreach ($categories as $parent => $children) {
            $parentCategory = Category::updateOrCreate(
                ['slug' => Str::slug($parent)],
                ['name' => $parent, 'parent_id' => null]
            );

            foreach ($children as $child) {
                Category::updateOrCreate(
                    ['slug' => Str::slug($child), 'parent_id' => $parentCategory->id],
                    ['name' => $child]
                );
            }
        }
    }
}