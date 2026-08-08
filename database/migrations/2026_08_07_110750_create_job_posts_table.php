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
        Schema::create('job_posts', function (Blueprint $table) {

            $table->id();

            // Job Owner
            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();

            // Category
            $table->foreignId('job_category_id')
                ->constrained('job_categories')
                ->cascadeOnDelete();

            // Job Information
            $table->string('title');

            $table->longText('description');

            $table->longText('about_us');

            $table->longText('what_you_do');

            $table->text('location')->nullable();;

            $table->enum('job_type', [
                'remote',
                'on-site',
                'part-time'
            ]);

            // Salary

            $table->string('currency')->default('NGN');

            $table->decimal('payment',15,2);

            $table->boolean('payment_required')
                ->default(false);

            // Recruitment

            $table->unsignedInteger('employee_needed')
                ->default(1);

            $table->string('additional_compensation')
                ->nullable();

            // Qualification

            $table->boolean('enable_qualification')
                ->default(false);

            $table->text('qualification')
                ->nullable();

            // Experience

            $table->boolean('enable_experience')
                ->default(false);

            $table->text('experience')
                ->nullable();

            $table->boolean('enable_year_experience')
                ->default(false);

            $table->unsignedInteger('year_experience')
                ->nullable();

            // Approval

            $table->enum('status',[
                'pending',
                'accepted',
                'declined'
            ])->default('pending');

            $table->foreignId('approved_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('approved_at')
                ->nullable();

            // Expiration

            $table->date('expire_date');

            $table->boolean('is_expired')
                ->default(false);

            // Statistics

            $table->unsignedInteger('views')
                ->default(0);

            $table->unsignedInteger('application_count')
                ->default(0);

            $table->timestamps();

            // Indexes

            $table->index('status');

            $table->index('job_type');

            $table->index('location');

            $table->index('expire_date');

            $table->index('payment');

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('job_posts');
    }
};