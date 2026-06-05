<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {

            $table->string('forward_source')
                ->nullable()
                ->after('forwarded_from');

            $table->string('forward_source_name')
                ->nullable()
                ->after('forward_source');

            $table->string('forward_source_image')
                ->nullable()
                ->after('forward_source_name');
        });
    }

    public function down(): void
        {
            if (Schema::getConnection()->getDriverName() !== 'sqlite') {
                Schema::table('messages', function (Blueprint $table) {
                    $table->dropColumn([
                        'is_forwarded',
                        'forwarded_from',
                        'forward_source',
                        'forward_source_name',
                        'forward_source_image',
                    ]);
                });
            }
        }
};