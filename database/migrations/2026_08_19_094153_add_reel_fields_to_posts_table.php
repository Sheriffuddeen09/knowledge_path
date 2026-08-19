<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table) {

            $table->string('post_type')
                ->default('post')
                ->after('user_id');

            $table->string('reel_type')
                ->nullable()
                ->after('post_type');

            $table->string('background_color')
                ->nullable()
                ->after('visibility');

            $table->string('font')
                ->nullable()
                ->after('background_color');

            $table->unsignedInteger('reel_duration')
                ->nullable()
                ->after('font');
        });
    }

    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {

            $table->dropColumn([
                'post_type',
                'reel_type',
                'background_color',
                'font',
                'reel_duration',
            ]);
        });
    }
};