<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teacher_forms', function (Blueprint $table) {
            if (Schema::hasColumn('teacher_forms', 'course_title')) {
                $table->dropColumn('course_title');
            }
        });
    }

    public function down(): void
    {
        Schema::table('teacher_forms', function (Blueprint $table) {
            $table->string('course_title');
        });
    }
};
