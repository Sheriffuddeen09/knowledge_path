<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('messages', function (Blueprint $table) {

            // 🔥 reference original message
            if (!Schema::hasColumn('messages', 'forwarded_from')) {
                $table->unsignedBigInteger('forwarded_from')->nullable()->after('id');

                $table->foreign('forwarded_from')
                    ->references('id')
                    ->on('messages')
                    ->nullOnDelete();
            }

            // 🔥 flag
            if (!Schema::hasColumn('messages', 'is_forwarded')) {
                $table->boolean('is_forwarded')->default(false)->after('forwarded_from');
            }
        });
    }

    public function down()
    {
        Schema::table('messages', function (Blueprint $table) {

            if (Schema::hasColumn('messages', 'forwarded_from')) {
                $table->dropForeign(['forwarded_from']);
                $table->dropColumn('forwarded_from');
            }

            if (Schema::hasColumn('messages', 'is_forwarded')) {
                $table->dropColumn('is_forwarded');
            }
        });
    }
};