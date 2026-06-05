<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {

            if (!Schema::hasColumn('messages', 'is_forwarded')) {
                $table->boolean('is_forwarded')
                    ->default(false)
                    ->after('file');
            }

            if (!Schema::hasColumn('messages', 'forwarded_from')) {
                $table->unsignedBigInteger('forwarded_from')
                    ->nullable()
                    ->after('is_forwarded');
            }

            if (!Schema::hasColumn('messages', 'forward_source')) {
                $table->string('forward_source')
                    ->nullable()
                    ->after('forwarded_from');
            }

            if (!Schema::hasColumn('messages', 'forward_source_name')) {
                $table->string('forward_source_name')
                    ->nullable()
                    ->after('forward_source');
            }

            if (!Schema::hasColumn('messages', 'forward_source_image')) {
                $table->string('forward_source_image')
                    ->nullable()
                    ->after('forward_source_name');
            }
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {

            $columns = [
                'is_forwarded',
                'forwarded_from',
                'forward_source',
                'forward_source_name',
                'forward_source_image',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('messages', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};