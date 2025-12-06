<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class UserFactory extends Factory
{
    public function definition()
    {
        return [
            'first_name' => $this->faker->firstName(),
            'last_name'  => $this->faker->lastName(),
            'dob' => $this->faker->date(),
            'phone' => $this->faker->phoneNumber(),
            'phone_country_code' => '+234',
            'location' => $this->faker->city(),
            'location_country_code' => 'NG',
            'email' => $this->faker->unique()->safeEmail(),
            'gender' => $this->faker->randomElement(['male', 'female']),
            'role' => $this->faker->randomElement(['student', 'admin']),
            'password' => bcrypt('password123'),
            'email_verified_at' => now(),
            'admin_choice' => null, // or default value
        ];
    }
}
