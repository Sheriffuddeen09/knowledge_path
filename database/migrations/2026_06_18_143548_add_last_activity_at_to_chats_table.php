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
                $table->timestamp('last_activity_at')->nullable()->index();
            });
        }

        public function down()
        {
            Schema::table('chats', function (Blueprint $table) {
                $table->dropColumn('last_activity_at');
            });
        }
};
