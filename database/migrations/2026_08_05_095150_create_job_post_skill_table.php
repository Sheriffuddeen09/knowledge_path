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
        Schema::create('job_post_skill', function (Blueprint $table) {

            $table->id();

            $table->foreignId('job_post_id')
                ->constrained('job_posts')
                ->cascadeOnDelete();

            $table->foreignId('job_skill_id')
                ->constrained('job_skills')
                ->cascadeOnDelete();

            $table->timestamps();

            $table->unique([
                'job_post_id',
                'job_skill_id'
            ]);

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('job_post_skill');
    }
};