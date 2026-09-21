<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('job_profiles', function (Blueprint $table) {
            $table->foreignId('job_category_id')
                ->nullable()
                ->after('type')
                ->constrained('job_categories')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('job_profiles', function (Blueprint $table) {
            $table->dropForeign(['job_category_id']);
            $table->dropColumn('job_category_id');
        });
    }
};