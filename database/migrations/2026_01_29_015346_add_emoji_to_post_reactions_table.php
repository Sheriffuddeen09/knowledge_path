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
            Schema::table('post_reactions', function (Blueprint $table) {
                $table->string('emoji', 10)->after('user_id');
            });
        }

    public function down()
        {
            Schema::table('post_reactions', function (Blueprint $table) {
                $table->dropColumn('emoji');
            });
        }

};
