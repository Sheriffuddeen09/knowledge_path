<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('chats', function (Blueprint $table) {
            $table->text('chat_key_user1')->nullable();
            $table->text('chat_key_user2')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('chats', function (Blueprint $table) {
            $table->dropColumn(['chat_key_user1', 'chat_key_user2']);
        });
    }
};