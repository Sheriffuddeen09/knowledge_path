<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teacher_requests', function (Blueprint $table) {

            $table->id();

            $table->foreignId('proposal_id')
                ->constrained('proposals')
                ->cascadeOnDelete();

            $table->foreignId('student_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->foreignId('teacher_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->foreignId('teacher_form_id')
                ->constrained('teacher_forms')
                ->cascadeOnDelete();

            $table->enum('status', [
                'pending',
                'accepted',
                'declined',
                'cancelled'
            ])->default('pending');

            $table->timestamps();

            // Prevent duplicate requests
            $table->unique([
                'proposal_id',
                'teacher_id'
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teacher_requests');
    }
};