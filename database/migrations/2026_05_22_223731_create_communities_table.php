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
        // ✅ DROP TABLE IF EXISTS
        Schema::dropIfExists('communities');

        Schema::create('communities', function (Blueprint $table) {

            $table->id();

            $table->foreignId('creator_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->foreignId('owner_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->string('community_name');

            $table->text('community_description')
                ->nullable();

            $table->string('community_image')
                ->nullable();

            $table->boolean('only_admin_can_message')
                ->default(true);

            $table->enum('disappearing_mode', [
                'off',
                '24h',
                '7d',
                '90d'
            ])->default('off');

            $table->boolean('allow_member_invite')
                ->default(false);

            $table->boolean('allow_member_reply')
                ->default(true);

            $table->string('invite_code')
                ->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('communities');
    }
};