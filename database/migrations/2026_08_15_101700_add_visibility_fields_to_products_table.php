<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {

            $table->string('visibility')
                ->nullable()
                ->after('location');

            $table->boolean('visibility_unlocked')
                ->default(false)
                ->after('visibility');

            $table->unsignedInteger('visibility_badges')
                ->default(0)
                ->after('visibility_unlocked');

            $table->timestamp('visibility_started_at')
                ->nullable()
                ->after('visibility_badges');

            $table->timestamp('visibility_expires_at')
                ->nullable()
                ->after('visibility_started_at');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {

            $table->dropColumn([
                'visibility',
                'visibility_unlocked',
                'visibility_badges',
                'visibility_started_at',
                'visibility_expires_at',
            ]);

        });
    }
};