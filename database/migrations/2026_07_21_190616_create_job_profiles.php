<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_profiles', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->enum('type', ['creator', 'finder']);

            // Job Creator
            $table->string('company_name')->nullable();
            $table->string('company_logo')->nullable();
            $table->string('company_address')->nullable();


            $table->enum('company_type', [
                'individual',
                'organisation'
            ])->nullable();;

            $table->string('organisation_size')->nullable();

            $table->string('company_location')->nullable();
            $table->string('location')->nullable();
            $table->string('address')->nullable();

            // Job Finder
            $table->string('full_name')->nullable();
            $table->string('cv')->nullable();
            $table->string('qualifications')->nullable();
            $table->text('portfolio')->nullable();
            $table->text('skills')->nullable();
            $table->text('certification')->nullable();

            $table->enum('status', [
                'pending',
                'approved',
                'declined'
            ])->default('pending');

            $table->text('decline_reason')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_profiles');
    }
};



