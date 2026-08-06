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
        Schema::create('job_applications', function (Blueprint $table) {

            $table->id();

            // Applied Job
            $table->foreignId('job_post_id')
                ->constrained('job_posts')
                ->cascadeOnDelete();

            // Job Finder
            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();

            // Job Poster
            $table->foreignId('job_owner_id')
                ->constrained('users')
                ->cascadeOnDelete();

            // Cover Letter
            $table->longText('cover_letter')
                ->nullable();

            // Optional Resume
            $table->string('resume')
                ->nullable();

            // Expected Salary
            $table->string('currency')
                ->default('NGN');

            $table->decimal('expected_salary',15,2)
                ->nullable();

            // Status
            $table->enum('status',[
                'pending',
                'reviewing',
                'shortlisted',
                'accepted',
                'rejected',
                'withdrawn'
            ])->default('pending');

            // Employer Remark
            $table->text('remark')
                ->nullable();

            // Reviewed By
            $table->foreignId('reviewed_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('reviewed_at')
                ->nullable();

            // Prevent duplicate applications
            $table->unique([
                'job_post_id',
                'user_id'
            ]);

            // Indexes
            $table->index('status');
            $table->index('job_owner_id');
            $table->index('reviewed_by');

            $table->timestamps();
        });
    }

   
    public function down(): void
    {
        Schema::dropIfExists('job_applications');
    }
};