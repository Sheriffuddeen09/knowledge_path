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
    Schema::table('exams', function (Blueprint $table) {
        $table->integer('duration_minutes')->after('title');
    });
}

public function down()
{
    Schema::table('exams', function (Blueprint $table) {
        $table->dropColumn('duration_minutes');
    });
}

};
