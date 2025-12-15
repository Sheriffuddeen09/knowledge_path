<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
       // database/migrations/xxxx_create_profile_visibilities_table.php
    Schema::create('profile_visibilities', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('user_id')->unique();
        $table->boolean('profile_visible')->default(true);
        $table->boolean('show_first_name')->default(true);
        $table->boolean('show_last_name')->default(true);
        $table->boolean('show_email')->default(true);
        $table->boolean('show_role')->default(true);
        $table->boolean('show_phone')->default(true);
        $table->boolean('show_dob')->default(true);
        $table->boolean('location')->default(true);
        $table->boolean('gender')->default(true);
        $table->boolean('password')->default(true);
        $table->timestamps();
    });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('profile_visibilities');
    }
};
