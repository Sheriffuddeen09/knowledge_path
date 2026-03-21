<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
{
    Schema::rename('messages', 'messages_old');

    Schema::create('messages', function (Blueprint $table) {
        $table->id();
        $table->foreignId('chat_id')->constrained()->cascadeOnDelete();
        $table->foreignId('sender_id');
        $table->string('type')->default('text');
        $table->string('file')->nullable();
        $table->timestamps();
    });

    // Disable FK for SQLite
    DB::statement('PRAGMA foreign_keys=OFF;');

    DB::statement("
        INSERT INTO messages (id, chat_id, sender_id, type, file, created_at, updated_at)
        SELECT id, chat_id, sender_id, type, file, created_at, updated_at FROM messages_old
    ");

    DB::statement('PRAGMA foreign_keys=ON;');

    Schema::dropIfExists('messages_old');
}

    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};
