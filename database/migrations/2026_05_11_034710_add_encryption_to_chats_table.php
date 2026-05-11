<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chats', function (Blueprint $table) {

            $table->text('security_code')
                ->nullable();

            $table->boolean('is_verified')
                ->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('chats', function (Blueprint $table) {

            $table->dropColumn([
                'security_code',
                'is_verified'
            ]);
        });
    }
};