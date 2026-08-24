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
        Schema::create('reel_media_descriptions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('post_media_id')
                ->constrained('post_media')
                ->cascadeOnDelete();

            $table->enum('type', [
                'image',
                'video',
            ]);

            $table->text('content');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reel_media_descriptions');
    }
};
