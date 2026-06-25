<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meeting_links', function (Blueprint $table) {

            $table->id();

            $table->string('room_id')->unique();

            $table->unsignedBigInteger(
                'creator_id'
            );

            $table->enum(
                'call_type',
                ['audio', 'video']
            );

            $table->timestamp(
                'expires_at'
            );

            $table->timestamps();

            $table->foreign(
                'creator_id'
            )
            ->references('id')
            ->on('users')
            ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'meeting_links'
        );
    }
};