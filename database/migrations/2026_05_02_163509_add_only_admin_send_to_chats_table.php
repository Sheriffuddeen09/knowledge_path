<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
{
    Schema::table('chats', function (Blueprint $table) {
        $table->boolean('only_admin_send')->default(0);
    });
}

public function down()
{
    Schema::table('chats', function (Blueprint $table) {
        $table->dropColumn('only_admin_send');
    });
}
};
