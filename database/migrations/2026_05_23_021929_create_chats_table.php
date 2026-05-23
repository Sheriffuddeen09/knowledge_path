<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  
    public function up(): void
    {
       Schema::create('chats', function (Blueprint $table) {
            $table->id();

            $table->enum('type', ['private', 'group', 'marketplace']);
             $table->foreignId('teacher_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('student_id')->nullable()->constrained('users')->nullOnDelete();

            $table->foreignId('user_one_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('user_two_id')->nullable()->constrained('users')->nullOnDelete();

            // ✅ group info
            $table->string('name')->nullable();
            $table->string('image')->nullable();

            $table->foreignId('created_by')->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->boolean('is_locked')->default(false);

            $table->timestamps();

             $table->unique(['teacher_id', 'student_id']);
             $table->unique(['user_one_id', 'user_two_id']);

            $table->unsignedBigInteger('user_one_last_read_id')->nullable();
            $table->unsignedBigInteger('user_two_last_read_id')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('chats');
    }
};
