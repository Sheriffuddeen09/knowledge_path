<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('live_class_requests', function (Blueprint $table) {
            $table->boolean('cleared_by_student')
                  ->default(false)
                  ->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('live_class_requests', function (Blueprint $table) {
            $table->dropColumn('cleared_by_student');
        });
    }
};
