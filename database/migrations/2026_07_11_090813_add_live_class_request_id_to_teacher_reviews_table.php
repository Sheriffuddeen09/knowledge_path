<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teacher_reviews', function (Blueprint $table) {

            $table->foreignId('live_class_request_id')
                ->nullable()
                ->after('teacher_request_id')
                ->constrained('live_class_requests')
                ->cascadeOnDelete();

        });
    }

    public function down(): void
    {
        Schema::table('teacher_reviews', function (Blueprint $table) {

            $table->dropForeign(['live_class_request_id']);
            $table->dropColumn('live_class_request_id');

        });
    }
};