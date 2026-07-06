<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teacher_requests', function (Blueprint $table) {

            $table->boolean('teacher_deleted')
                ->default(false)
                ->after('status');

        });
    }

    public function down(): void
    {
        Schema::table('teacher_requests', function (Blueprint $table) {

            $table->dropColumn('teacher_deleted');

        });
    }
};