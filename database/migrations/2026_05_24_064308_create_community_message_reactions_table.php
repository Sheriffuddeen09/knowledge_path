<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'community_message_reactions',
            function (Blueprint $table) {

                $table->id();

                $table->foreignId(
                    'community_message_id'
                )->constrained(
                    'community_messages'
                )->cascadeOnDelete();

                $table->foreignId(
                    'user_id'
                )->constrained()->cascadeOnDelete();

                $table->string('emoji');

                $table->timestamps();
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'community_message_reactions'
        );
    }
};