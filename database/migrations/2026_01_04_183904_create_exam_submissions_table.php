<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('exam_submissions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('exam_id')
                  ->constrained()
                  ->cascadeOnDelete();

            $table->foreignId('student_id')
                  ->constrained('users')
                  ->cascadeOnDelete();

            $table->json('answers')->nullable();
            $table->integer('current_index')->default(0);
            $table->integer('remaining_seconds')->nullable();

            $table->timestamp('started_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->boolean('is_extended')->default(false);
            $table->timestamp('last_save_at')->nullable();
            $table->timestamp('extended_until')->nullable();
            $table->unsignedTinyInteger('reschedule_count')->default(0);

            $table->timestamps();

            // one submission per student per exam
            $table->unique(['exam_id', 'student_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_submissions');
    }
};
