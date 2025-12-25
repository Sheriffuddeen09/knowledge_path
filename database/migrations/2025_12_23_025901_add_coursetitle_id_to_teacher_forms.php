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
    Schema::table('teacher_forms', function (Blueprint $table) {
        $table->foreignId('coursetitle_id')
              ->nullable()
              ->constrained('coursetitles')
              ->onDelete('cascade');
    });
}

public function down()
{
    Schema::table('teacher_forms', function (Blueprint $table) {
        $table->dropForeign(['coursetitle_id']);
        $table->dropColumn('coursetitle_id');
    });
}

};
