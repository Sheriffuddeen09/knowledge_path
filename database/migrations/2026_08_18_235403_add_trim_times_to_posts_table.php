<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->decimal('trim_start', 10, 3)
                ->nullable()
                ->after('content');

            $table->decimal('trim_end', 10, 3)
                ->nullable()
                ->after('trim_start');
        });
    }

    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->dropColumn([
                'trim_start',
                'trim_end',
            ]);
        });
    }
};