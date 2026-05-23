<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chats', function (Blueprint $table) {

            $table->boolean('only_admin_send')
                ->default(false)
                ->after('created_by');

        });
    }

    public function down(): void
    {
        Schema::table('chats', function (Blueprint $table) {

            $table->dropColumn('only_admin_send');

        });
    }
};