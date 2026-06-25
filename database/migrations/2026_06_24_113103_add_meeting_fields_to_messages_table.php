<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {

            $table->string('meeting_room_id')
                ->nullable()
                ->after('message');

            $table->string('meeting_call_type')
                ->nullable()
                ->after('meeting_room_id');

            $table->timestamp('meeting_expires_at')
                ->nullable()
                ->after('meeting_call_type');
            $table->text('meeting_link')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {

            $table->dropColumn([
                'meeting_room_id',
                'meeting_call_type',
                'meeting_expires_at',
            ]);

        });
    }
};