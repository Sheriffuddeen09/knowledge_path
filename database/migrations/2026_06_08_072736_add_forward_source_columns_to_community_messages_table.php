<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('community_messages', function (Blueprint $table) {

            $table->unsignedBigInteger('forward_source_message_id')
                ->nullable()
                ->after('is_pinned');

            $table->unsignedBigInteger('forward_source_community_id')
                ->nullable()
                ->after('forward_source_message_id');
        });
    }

    public function down(): void
    {
        Schema::table('community_messages', function (Blueprint $table) {

            $table->dropColumn([
                'forward_source_message_id',
                'forward_source_community_id',
            ]);
        });
    }
};