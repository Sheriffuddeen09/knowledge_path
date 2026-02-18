<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    
    public function run(): void
    {
        // User::factory(10)->create();

        User::create([
                        'first_name' => 'Test',
                        'last_name' => 'User',
                        'dob' => '2000-01-01',
                        'phone' => '08012345678',
                        'phone_country_code' => '+234',
                        'location' => 'Lagos',
                        'location_country_code' => 'NG',
                        'email' => 'test@example.com',
                        'gender' => 'male',
                        'role' => 'student',
                        'password' => bcrypt('password'),
                        'email_verified_at' => now(),
                        'admin_choice' => null,
                    ]);

    }
}
