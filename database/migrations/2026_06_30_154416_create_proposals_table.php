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
        Schema::create('proposals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')
                ->constrained('users')
                ->cascadeOnDelete();
            $table->string('title');
            $table->string('subject')->nullable();
            $table->decimal('price', 10, 2);
            $table->string('currency', 10);
            $table->enum('teacher_type', [
                'male',
                'female',
                'any'
            ])->default('any');
            $table->enum('teaching_mode', [
                'online',
                'physical'
            ]);
            $table->string('preferred_location')->nullable();
            $table->string('qualification')->nullable();
            $table->integer('teaching_hours');
            $table->time('from_time')->nullable()->after('teaching_hours');
            $table->time('to_time')->nullable()->after('from_time');
            $table->longText('description');
            $table->enum('status', [
                'open',
                'closed',
                'completed'
            ])->default('open');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proposals');
    }
};
