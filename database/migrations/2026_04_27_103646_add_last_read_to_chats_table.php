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
            $table->unsignedBigInteger('user_one_last_read_id')->nullable();
            $table->unsignedBigInteger('user_two_last_read_id')->nullable();
        });
    }

    public function down()
    {
        Schema::table('chats', function (Blueprint $table) {
            $table->dropColumn([
                'user_one_last_read_id',
                'user_two_last_read_id'
            ]);
        });
    }
};
