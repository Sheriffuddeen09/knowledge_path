<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {

            if (!Schema::hasColumn('messages', 'message')) {
                $table->text('message')->nullable();
            }

            if (!Schema::hasColumn('messages', 'iv')) {
                $table->string('iv')->nullable();
            }

        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {

            if (Schema::hasColumn('messages', 'message')) {
                $table->dropColumn('message');
            }

            if (Schema::hasColumn('messages', 'iv')) {
                $table->dropColumn('iv');
            }

        });
    }
};