<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->boolean('is_live')
                ->default(false)
                ->after('post_type');

            $table->enum('live_status', [
                'none',
                'live',
                'ended',
            ])
                ->default('none')
                ->after('is_live');

            $table->string('live_room_name')
                ->nullable()
                ->unique()
                ->after('live_status');

            $table->timestamp('live_started_at')
                ->nullable()
                ->after('live_room_name');

            $table->timestamp('live_ended_at')
                ->nullable()
                ->after('live_started_at');

            $table->unsignedInteger('live_viewers_count')
                ->default(0)
                ->after('live_ended_at');
        });
    }

    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->dropColumn([
                'is_live',
                'live_status',
                'live_room_name',
                'live_started_at',
                'live_ended_at',
                'live_viewers_count',
            ]);
        });
    }
};