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
    Schema::table('posts', function (Blueprint $table) {
        $table->foreignId('original_post_id')
              ->nullable()
              ->constrained('posts')
              ->cascadeOnDelete();

        $table->enum('visibility', ['public','friends','private'])
              ->default('public');
    });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            //
        });
    }
};
