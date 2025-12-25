<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Coursetitle;
use Illuminate\Support\Str;

class CoursetitleSeeder extends Seeder
{
    public function run()
    {
        $coursetitles = [
            "Hadith",
            "Qur'an",
            "Fiqh",
            "Poem",
            "Nahwu",
            "Sorf",
            "Other"
        ];

        foreach ($coursetitles as $name) {
            Coursetitle::create([
                'name' => $name,
                'slug' => Str::slug($name),
            ]);
        }
    }
}
