<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->boolean('is_forwarded')
                ->default(false)
                ->after('file');

            $table->unsignedBigInteger('forwarded_from')
                ->nullable()
                ->after('is_forwarded');

            $table->string('forward_source')
                ->nullable()
                ->after('forwarded_from');

            $table->string('forward_source_name')
                ->nullable()
                ->after('forward_source');

            $table->string('forward_source_image')
                ->nullable()
                ->after('forward_source_name');

            $table->unsignedBigInteger('forward_source_message_id')
                ->nullable()
                ->after('forward_source_image');

            $table->unsignedBigInteger('forward_source_community_id')
                ->nullable()
                ->after('forward_source_message_id');
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropColumn([
                'is_forwarded',
                'forwarded_from',
                'forward_source',
                'forward_source_name',
                'forward_source_image',
                'forward_source_message_id',
                'forward_source_community_id',
            ]);
        });
    }
};