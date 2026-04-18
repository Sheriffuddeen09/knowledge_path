<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
        {
            Schema::create('messages', function (Blueprint $table) {
                $table->id();
                $table->foreignId('chat_id')->constrained()->cascadeOnDelete();
                $table->foreignId('sender_id');
                $table->string('type')->default('text');
                $table->string('file')->nullable();
                $table->timestamps();
            });
        }

    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};
