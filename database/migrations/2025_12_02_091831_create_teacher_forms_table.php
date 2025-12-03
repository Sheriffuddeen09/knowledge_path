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
    Schema::create('teacher_forms', function ($table) {
        $table->id();
        $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
        $table->string('course_title');
        $table->decimal('course_payment', 10, 2);
        $table->string('currency');
        $table->string('compliment')->nullable();
        $table->string('logo')->nullable();
        $table->string('cv')->nullable();
        $table->string('qualification');
        $table->string('experience');
        $table->string('specialization')->nullable();
        $table->timestamps();
    });
}

public function down(): void
{
    Schema::dropIfExists('teacher_forms');
}

};
