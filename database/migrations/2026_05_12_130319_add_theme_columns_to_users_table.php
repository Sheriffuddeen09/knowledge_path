<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {

            $table->string('theme_mode')
                  ->default('light');

            $table->string('theme_color')
                  ->default('blue');

            $table->string('text_color')
                  ->default('auto');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {

            $table->dropColumn([
                'theme_mode',
                'theme_color',
                'text_color'
            ]);
        });
    }
};