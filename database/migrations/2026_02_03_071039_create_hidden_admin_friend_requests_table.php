<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     */
    public function up(): void
    {
        Schema::create('hidden_admin_friend_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('admin_id')->constrained('admin_friend_requests')->cascadeOnDelete();
            $table->timestamp('hidden_until');
            $table->timestamps();

            $table->unique(['user_id', 'admin_id']); // prevent duplicates
        });


    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hidden_admin_friend_requests');
    }
};
