<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reel_reactions', function (Blueprint $table) {

            $table->id();

            $table->foreignId('post_id')
                ->constrained('posts')
                ->cascadeOnDelete();

            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->string('reaction', 50);

            $table->timestamps();

            $table->unique([
                'post_id',
                'user_id'
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reel_reactions');
    }
};