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

            $table->foreignId('job_id')
            ->constrained()
            ->cascadeOnDelete();

            $table->foreignId('job_finder_id')
            ->constrained('users')
            ->cascadeOnDelete();


            $table->text('message')
            ->nullable();

            $table->enum('status',[
                'pending',
                'accepted',
                'rejected'
            ])->default('pending');


            $table->timestamps();

        });
    }


    public function down(): void
    {
        Schema::dropIfExists('job_applications');
    }
};