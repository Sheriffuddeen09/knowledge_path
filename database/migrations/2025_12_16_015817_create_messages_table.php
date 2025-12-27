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
        Schema::create('messages', function (Blueprint $table) {
                $table->id();
                $table->foreignId('chat_id')->constrained()->cascadeOnDelete();
                $table->foreignId('sender_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('receiver_id')->constrained('users')->cascadeOnDelete();

                $table->enum('type', ['text', 'image', 'voice']);
                $table->text('message')->nullable();
                $table->string('file')->nullable();
                $table->timestamp('seen_at')->nullable();

                $table->foreignId('replied_to')->nullable()->constrained('messages')->nullOnDelete();
                $table->boolean('edited')->default(false);
                $table->timestamps();
            });


        
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};
