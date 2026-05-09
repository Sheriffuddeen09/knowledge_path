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
    if (!Schema::hasTable('groups')) {
        Schema::create('groups', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('image')->nullable();
            $table->foreignId('created_by')->nullable();
            $table->boolean('only_admin_send')->default(false);
            $table->foreignId('chat_id')->nullable()->constrained();
            $table->timestamps();
        });
    }
}

public function down(): void
{
    Schema::dropIfExists('groups');
}
};
