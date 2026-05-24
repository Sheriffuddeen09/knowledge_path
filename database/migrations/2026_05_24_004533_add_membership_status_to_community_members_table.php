<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
        {
            Schema::table(
                'community_members',
                function (Blueprint $table) {

                $table->string(
                    'membership_status'
                )
                ->default('approved')
                ->after('role');

            });
        }

        public function down()
        {
            Schema::table(
                'community_members',
                function (Blueprint $table) {

                $table->dropColumn(
                    'membership_status'
                );

            });
        }
};
