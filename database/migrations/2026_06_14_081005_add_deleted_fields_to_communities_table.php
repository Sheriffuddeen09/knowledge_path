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
    Schema::table('communities', function (Blueprint $table) {
        $table->boolean('is_deleted')
            ->default(false);

        $table->foreignId('deleted_by')
            ->nullable()
            ->constrained('users')
            ->nullOnDelete();

        $table->timestamp('deleted_at')
            ->nullable();
    });
}

public function down()
{
    Schema::table('communities', function (Blueprint $table) {
        $table->dropColumn([
            'is_deleted',
            'deleted_by',
            'deleted_at',
        ]);
    });
}
};
