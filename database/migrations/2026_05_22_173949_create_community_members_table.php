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
        Schema::create('community_members', function (Blueprint $table) {

            $table->id();

            $table->foreignId('community_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->enum('role', [
                'owner',
                'admin',
                'member'
            ])->default('member');

            $table->boolean('can_message')
                ->default(false);

            $table->boolean('muted')
                ->default(false);

            $table->timestamp('joined_at')->nullable();

            $table->timestamps();

            $table->unique([
                'community_id',
                'user_id'
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('community_members');
    }
};
