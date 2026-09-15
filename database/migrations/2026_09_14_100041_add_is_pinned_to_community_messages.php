<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('community_messages', function (Blueprint $table) {
            if (!Schema::hasColumn('community_messages', 'is_pinned')) {
                $table->boolean('is_pinned')
                    ->default(false)
                    ->after('id');
            }

            if (!Schema::hasColumn('community_messages', 'pin_expires_at')) {
                $table->timestamp('pin_expires_at')
                    ->nullable()
                    ->after('is_pinned');
            }
        });
    }

    public function down(): void
    {
        Schema::table('community_messages', function (Blueprint $table) {
            if (Schema::hasColumn('community_messages', 'pin_expires_at')) {
                $table->dropColumn('pin_expires_at');
            }

            if (Schema::hasColumn('community_messages', 'is_pinned')) {
                $table->dropColumn('is_pinned');
            }
        });
    }
};