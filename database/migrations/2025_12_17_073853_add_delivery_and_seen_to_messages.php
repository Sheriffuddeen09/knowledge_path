<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
{
    Schema::table('messages', function (Blueprint $table) {
        if (!Schema::hasColumn('messages', 'delivered_at')) {
            $table->timestamp('delivered_at')->nullable();
        }

        if (!Schema::hasColumn('messages', 'seen_at')) {
            $table->timestamp('seen_at')->nullable();
        }
    });
}


    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropColumn(['delivered_at', 'seen_at']);
        });
    }
};
