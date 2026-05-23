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
        Schema::create('community_messages', function (Blueprint $table) {

                $table->id();

                $table->foreignId('community_id')
                    ->constrained()
                    ->cascadeOnDelete();

                $table->foreignId('sender_id')
                    ->constrained('users')
                    ->cascadeOnDelete();

                $table->enum('type', [
                    'text',
                    'image',
                    'voice',
                    'video',
                    'file'
                ])->default('text');

                $table->longText('message')->nullable();

                $table->string('file')->nullable();

                $table->boolean('edited')
                    ->default(false);

                $table->foreignId('replied_to')
                    ->nullable()
                    ->constrained('community_messages')
                    ->nullOnDelete();

                $table->timestamp('deleted_at')
                    ->nullable();

                $table->timestamps();
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('community_messages');
    }
};
