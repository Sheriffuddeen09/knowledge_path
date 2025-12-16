<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateLiveClassRequestsTable extends Migration
{
    public function up(): void
    {
        Schema::create('live_class_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');       // Student who sent request
            $table->unsignedBigInteger('teacher_id');    // Teacher being requested
            $table->enum('status', ['pending', 'accepted', 'declined'])->default('pending');
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('teacher_id')->references('id')->on('users')->onDelete('cascade');
            $table->unique(['user_id', 'teacher_id']); // prevent duplicate requests
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('live_class_requests');
    }
}
