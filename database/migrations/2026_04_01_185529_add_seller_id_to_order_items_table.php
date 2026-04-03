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
            Schema::table('order_items', function (Blueprint $table) {
                $table->unsignedBigInteger('seller_id')->nullable()->after('product_id');
            });
        }

    public function down()
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn('seller_id');
        });
    }
};
