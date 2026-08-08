<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_applications', function (Blueprint $table) {

            $table->id();

            $table->foreignId('job_post_id')
                ->constrained('job_posts')
                ->cascadeOnDelete();

            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            // Optional CV
            $table->string('cv')->nullable();

            // Optional applicant message
            $table->text('additional_text')->nullable();

            // These become required only when enabled by the job
            $table->string('qualification')->nullable();

            $table->text('experience')->nullable();

            $table->decimal('year_experience', 5, 2)
                ->nullable();

            // Applicant's payment information/expectation
            $table->decimal('payment', 15, 2)
                ->nullable();

            $table->string('currency', 10)
                ->nullable();

            $table->enum('status', [
                'pending',
                'reviewed',
                'shortlisted',
                'accepted',
                'rejected'
            ])->default('pending');

            $table->timestamp('reviewed_at')
                ->nullable();

            $table->timestamps();

            // One user should not apply to the same job twice
            $table->unique([
                'job_post_id',
                'user_id'
            ]);

        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_applications');
    }
};