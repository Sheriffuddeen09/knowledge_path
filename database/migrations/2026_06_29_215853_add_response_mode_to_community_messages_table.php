<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
{
    Schema::table('community_messages', function (Blueprint $table) {

        $table->boolean('response_mode')
            ->default(true);

    });
}

public function down(): void
{
    Schema::table('community_messages', function (Blueprint $table) {

        $table->dropColumn('response_mode');

    });
}
};
