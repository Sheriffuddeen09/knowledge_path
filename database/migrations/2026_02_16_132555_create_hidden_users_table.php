<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    
    public function up(): void
    {

        Schema::create('hidden_users', function (Blueprint $table) {
            $table->id();

            // Who is hiding
            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();

            // Who is being hidden
            $table->foreignId('hidden_user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->timestamps();

            $table->unique(['user_id', 'hidden_user_id']);
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hidden_users');
    }
};
