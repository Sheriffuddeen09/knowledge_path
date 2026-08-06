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
        Schema::create('job_post_views', function (Blueprint $table) {

            $table->id();

            // Job Post
            $table->foreignId('job_post_id')
                ->constrained('job_posts')
                ->cascadeOnDelete();

            // Logged-in User (nullable for guests)
            $table->foreignId('user_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            // Visitor Information
            $table->ipAddress('ip_address')->nullable();

            $table->string('device')->nullable();

            $table->string('browser')->nullable();

            $table->string('platform')->nullable();

            $table->string('country')->nullable();

            $table->string('city')->nullable();

            // Referral
            $table->string('referrer')->nullable();

            // Timestamp
            $table->timestamp('viewed_at')->useCurrent();

            $table->timestamps();

            // Indexes
            $table->index('job_post_id');
            $table->index('user_id');
            $table->index('viewed_at');
            $table->index('ip_address');

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('job_post_views');
    }
};