<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->foreignId('advertisement_id')
                ->nullable()
                ->after('user_id')
                ->constrained('advertisements')
                ->nullOnDelete();

            $table->index('advertisement_id');
        });
    }

    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->dropForeign(['advertisement_id']);
            $table->dropIndex(['advertisement_id']);
            $table->dropColumn('advertisement_id');
        });
    }
};