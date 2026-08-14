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
                ->default('location')
                ->after('user_id');

            $table->unsignedInteger('visibility_badges')
                ->default(0)
                ->after('visibility');

            $table->boolean('visibility_unlocked')
                ->default(false)
                ->after('visibility_badges');

            $table->timestamp('visibility_unlocked_at')
                ->nullable()
                ->after('visibility_unlocked');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {

            $table->dropColumn([
                'visibility',
                'visibility_badges',
                'visibility_unlocked',
                'visibility_unlocked_at',
            ]);

        });
    }
};