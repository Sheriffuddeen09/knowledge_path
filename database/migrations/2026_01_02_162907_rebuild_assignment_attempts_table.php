<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::rename('assignment_attempts', 'assignment_attempts_old');

        Schema::create('assignment_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assignment_id');
            $table->foreignId('student_id');
            $table->json('answers')->nullable();
            $table->integer('current_index')->default(0);
            $table->integer('remaining_seconds')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->integer('reschedule_count')->default(0);
            $table->timestamps();
        });

        DB::statement("
            INSERT INTO assignment_attempts 
            (id, assignment_id, student_id, answers, current_index, remaining_seconds, started_at, created_at, updated_at)
            SELECT id, assignment_id, student_id, answers, current_index, remaining_seconds, started_at, created_at, updated_at
            FROM assignment_attempts_old
        ");

        Schema::drop('assignment_attempts_old');
    }

    public function down(): void {}
};
