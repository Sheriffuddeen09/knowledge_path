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
    Schema::table('assignment_answers', function (Blueprint $table) {
        $table->foreignId('assignment_result_id')
            ->nullable()
            ->after('id')
            ->constrained('assignment_results')
            ->cascadeOnDelete();
    });
}

public function down(): void
{
    Schema::table('assignment_answers', function (Blueprint $table) {
        $table->dropForeign(['assignment_result_id']);
        $table->dropColumn('assignment_result_id');
    });
}

};
