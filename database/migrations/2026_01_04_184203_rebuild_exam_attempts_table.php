<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::rename('exam_attempts', 'exam_attempts_old');

        Schema::create('exam_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_id');
            $table->foreignId('student_id');
            $table->json('answers')->nullable();
            $table->integer('current_index')->default(0);
            $table->integer('remaining_seconds')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->integer('reschedule_count')->default(0);
            $table->timestamps();
        });

        DB::statement("
            INSERT INTO exam_attempts 
            (id, exam_id, student_id, answers, current_index, remaining_seconds, started_at, created_at, updated_at)
            SELECT id, exam_id, student_id, answers, current_index, remaining_seconds, started_at, created_at, updated_at
            FROM exam_attempts_old
        ");

        Schema::drop('exam_attempts_old');
    }

    public function down(): void {}
};
