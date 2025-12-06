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
            "Hadith",
            "Qur'an",
            "Islamic Reminder",
            "Marriage",
            "Fiqh",
            "Dua & Dhikr",
            "Poem",
            "Other"
        ];

        foreach ($categories as $name) {
            Category::create([
                'name' => $name,
                'slug' => Str::slug($name),
            ]);
        }
    }
}
