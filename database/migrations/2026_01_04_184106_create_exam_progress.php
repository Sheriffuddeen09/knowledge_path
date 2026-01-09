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
        Schema::create('exam_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_exam_id');
            $table->json('answers')->nullable();
            $table->integer('current_index')->default(0);
            $table->integer('remaining_seconds');
            $table->timestamps();
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('exam_progress');
    }
};
