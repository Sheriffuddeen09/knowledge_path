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
       Schema::create('chats', function (Blueprint $table) {
    $table->id();

    // student ↔ teacher
    $table->foreignId('teacher_id')->nullable()->constrained('users')->nullOnDelete();
    $table->foreignId('student_id')->nullable()->constrained('users')->nullOnDelete();

    // student ↔ student
    $table->foreignId('user_one_id')->nullable()->constrained('users')->nullOnDelete();
    $table->foreignId('user_two_id')->nullable()->constrained('users')->nullOnDelete();

    $table->string('type'); // student_teacher | student_student
    $table->timestamps();

    // prevent duplicates
    $table->unique(['teacher_id', 'student_id']);
    $table->unique(['user_one_id', 'user_two_id']);
});

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('chats');
    }
};
