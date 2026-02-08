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
            Schema::create('hidden_student_friend_requests', function (Blueprint $table) {
        $table->id();

        $table->foreignId('user_id')->constrained()->cascadeOnDelete(); 
        // who hid it

        $table->foreignId('student_friend_request_id')
            ->constrained('student_friend_requests')
            ->cascadeOnDelete();

        $table->timestamp('hidden_until')->nullable();
        $table->timestamps();

        $table->unique(['user_id', 'student_friend_request_id']);
    });



    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hidden_student_friend_requests');
    }
};
