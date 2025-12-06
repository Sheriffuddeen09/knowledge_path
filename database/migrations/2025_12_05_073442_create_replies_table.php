<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('replies', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('comment_id');
            $table->unsignedBigInteger('user_id');

            $table->text('reply');
            $table->string('emoji', 50)->nullable();
            $table->json('likes')->nullable();

            $table->timestamps();

            // Foreign keys
            $table->foreign('comment_id')
                  ->references('id')->on('comments')
                  ->onDelete('cascade');

            $table->foreign('user_id')
                  ->references('id')->on('users')
                  ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('replies');
    }
};
