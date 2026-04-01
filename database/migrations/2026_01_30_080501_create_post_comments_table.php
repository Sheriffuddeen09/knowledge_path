<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('post_comments', function (Blueprint $table) {
            $table->id();

            // SAFE: no constrained() (prevents SQLite crash)
            $table->unsignedBigInteger('post_id');
            $table->unsignedBigInteger('user_id');

            // self-reply support
            $table->unsignedBigInteger('parent_id')->nullable();

            $table->text('body')->nullable();
            $table->string('image')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('post_comments');
    }
};