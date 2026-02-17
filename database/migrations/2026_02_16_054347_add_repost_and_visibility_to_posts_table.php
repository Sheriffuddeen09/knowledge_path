<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
{
    Schema::table('posts', function (Blueprint $table) {

        $table->foreignId('original_post_id')
              ->nullable()
              ->after('user_id')
              ->constrained('posts')
              ->cascadeOnDelete();

        $table->enum('visibility', ['public', 'friends', 'private'])
              ->default('public')
              ->after('original_post_id');
    });
}

public function down(): void
{
    Schema::table('posts', function (Blueprint $table) {

        $table->dropForeign(['original_post_id']);
        $table->dropColumn(['original_post_id', 'visibility']);
    });
}


};
