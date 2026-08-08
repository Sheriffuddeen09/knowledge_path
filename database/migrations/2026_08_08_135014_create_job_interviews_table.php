<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_interviews', function (Blueprint $table) {

            $table->id();

            $table->foreignId('job_application_id')
                ->constrained('job_applications')
                ->cascadeOnDelete();

            $table->string('interview_token')
                ->unique();

            $table->date('interview_date');

            $table->time('interview_time');

            $table->string('meeting_link');

            $table->enum('status', [
                'scheduled',
                'completed',
                'cancelled'
            ])->default('scheduled');

            $table->text('notes')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_interviews');
    }
};