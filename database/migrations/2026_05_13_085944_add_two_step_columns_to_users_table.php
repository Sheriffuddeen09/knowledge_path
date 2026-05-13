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
            Schema::table('users', function ($table) {

                $table->boolean(
                    'two_step_enabled'
                )->default(false);

                $table->string(
                    'two_step_pin'
                )->nullable();

                $table->timestamp(
                    'two_step_verified_at'
                )->nullable();
            });
        }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            //
        });
    }
};
