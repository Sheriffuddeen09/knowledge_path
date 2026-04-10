<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
{
    Schema::table('messages', function (Blueprint $table) {

        if (!Schema::hasColumn('messages', 'duration')) {
            $table->integer('duration')->nullable();
        }

        if (!Schema::hasColumn('messages', 'file_name')) {
            $table->string('file_name')->nullable();
        }

    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            //
        });
    }
};
