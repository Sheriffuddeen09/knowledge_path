<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();

            // full details for registration
            $table->string('first_name');
            $table->string('last_name');
            $table->date('dob');
            $table->string('phone')->unique();
            $table->string('phone_country_code');
            $table->string('location');
            $table->string('location_country_code');
            $table->string('email')->unique();
            $table->string('gender');   // male, female, other

            // role handling
            $table->enum('role', ['student', 'admin']);

            // admin extra fields
            $table->string('admin_choice')->nullable(); 
            // store choice: "sell_online", "create_free_content", "arabic_teacher"

            // teacher form details
            $table->json('teacher_info')->nullable();

            // login + security
            $table->string('password');
            $table->timestamp('email_verified_at')->nullable();
            $table->rememberToken();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
