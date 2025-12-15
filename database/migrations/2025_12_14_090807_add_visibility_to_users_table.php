<?php 

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->json('visibility')->nullable()->default(json_encode([
                'dob' => true,
                'location' => true,
                'email' => true,
                'first_name' => true,
                'last_name' => true,
                'role' => true,
                'gender' => true,
                'password' => true,
                'phone' => true
            ]));
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('visibility');
        });
    }
};
