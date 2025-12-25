<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('message_reactions', function (Blueprint $table) {
            if (!Schema::hasColumn('message_reactions', 'emoji')) {
                $table->string('emoji')->default('')->nullable(false);
            }
        });
    }

    public function down(): void
    {
        Schema::table('message_reactions', function (Blueprint $table) {
            if (Schema::hasColumn('message_reactions', 'emoji')) {
                $table->dropColumn('emoji');
            }
        });
    }
};
