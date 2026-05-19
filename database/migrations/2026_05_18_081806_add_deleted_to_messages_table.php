<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {

    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {

            // ✅ only add if not exists
            if (!Schema::hasColumn('messages', 'deleted')) {

                $table->boolean('deleted')
                    ->default(false)
                    ->after('edited');
            }
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {

            // ✅ only drop if exists
            if (Schema::hasColumn('messages', 'deleted')) {

                $table->dropColumn('deleted');
            }
        });
    }
};