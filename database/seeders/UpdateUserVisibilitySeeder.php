<?php 

use Illuminate\Database\Seeder;
use App\Models\User;

class UpdateUserVisibilitySeeder extends Seeder
{
    public function run()
    {
        User::query()->update([
            'visibility' => json_encode([
                'dob' => true,
                'location' => true,
                'email' => true,
                'first_name' => true,
                'last_name' => true,
                'role' => true,
                'gender' => true,
                'password' => true,
                'phone' => true
            ])
        ]);
    }
}
