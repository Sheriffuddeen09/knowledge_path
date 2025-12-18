<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // SQLite doesn't support modifying CHECK constraints directly
        // So you may need to recreate the table or remove the constraint if possible

        DB::statement("ALTER TABLE messages RENAME TO messages_old");

        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chat_id')->constrained()->onDelete('cascade');
            $table->foreignId('sender_id')->constrained('users')->onDelete('cascade');
            $table->string('type'); // allowed: text, image, file, audio
            $table->string('file')->nullable();
            $table->timestamps();
        });

        // copy old data
        DB::statement("INSERT INTO messages (id, chat_id, sender_id, type, file, created_at, updated_at)
                       SELECT id, chat_id, sender_id, type, file, created_at, updated_at FROM messages_old");

        DB::statement("DROP TABLE messages_old");
    }

    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};
