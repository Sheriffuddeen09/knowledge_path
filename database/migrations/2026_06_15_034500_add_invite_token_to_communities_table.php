<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{

public function up()
{
    Schema::table('communities', function (Blueprint $table) {
        $table->string('invite_token')
              ->nullable()
              ->unique()
              ->after('deleted_at');
    });
}

public function down()
{
    Schema::table('communities', function (Blueprint $table) {
        $table->dropColumn('invite_token');
    });
}
};
