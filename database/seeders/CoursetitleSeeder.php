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

    // ===== Islamic Studies =====
    "Qur'an",
    "Hifz (Qur'an Memorization)",
    "Tajweed",
    "Tafsir",
    "Hadith",
    "Mustalah al-Hadith",
    "Fiqh",
    "Usul al-Fiqh",
    "Tawheed",
    "Aqeedah",
    "Seerah",
    "Islamic History",
    "Islamic Poetry",

    // ===== Arabic =====
    "Arabic Language",
    "Nahwu",
    "Sarf",
   
    // ===== Languages =====
    "English Language",
    "English Literature",
    "French",

    // ===== Mathematics =====
    "Mathematics",
    "Further Mathematics",
    "Statistics",

    // ===== Sciences =====
    "Physics",
    "Chemistry",
    "Biology",
    "Agricultural Science",
    "Health Science",
    "Environmental Science",

    // ===== Computing =====
    "Computer Science",
    "ICT",
    "Programming",
    "Web Development",
    "Mobile App Development",
    "UI/UX Design",
    "Graphics Design",
    "Cyber Security",
    "Artificial Intelligence",
    "Machine Learning",
    "Data Science",

    // ===== Business =====
    "Accounting",
    "Economics",
    "Commerce",
    "Business Studies",
    "Entrepreneurship",
    "Marketing",

    // ===== Arts =====
    "Literature",
    "Government",
    "Geography",
    "History",
    "Civic Education",
    "Philosophy",
    "Psychology",

    // ===== Vocational =====
    "Tailoring",
    "Fashion Design",
    "Video Editing",
    "Catering",
    "Baking",
    "Cooking",
    "Interior Design",

    // ===== Professional =====
    "Project Management",
    
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
