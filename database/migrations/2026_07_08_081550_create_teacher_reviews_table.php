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
        Schema::create('teacher_reviews', function (Blueprint $table) {
            $table->id();

            $table->foreignId('teacher_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->foreignId('student_id')
                ->constrained('users')
                ->cascadeOnDelete();

            // Optional: ensure review is for an accepted request
            $table->foreignId('teacher_request_id')
                ->nullable()
                ->constrained('teacher_requests')
                ->cascadeOnDelete();

            $table->tinyInteger('rating'); // 1 - 5

            $table->text('review')->nullable();

            $table->timestamps();

            // One review per student per accepted request
            $table->unique(['student_id', 'teacher_request_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('teacher_reviews');
    }
};
