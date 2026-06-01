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
        Schema::create('community_message_approvals', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('message_id'); // original message
            $table->unsignedBigInteger('admin_id');
            $table->text('admin_response')->nullable();
            $table->string('status')->default('approved');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('community_message_approvals');
    }
};
