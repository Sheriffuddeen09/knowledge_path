<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 🔥 Drop table if it already exists
        Schema::dropIfExists('community_reports');

        // 🔥 Recreate table
        Schema::create('community_reports', function (Blueprint $table) {

            $table->id();

            $table->foreignId('community_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('reporter_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->foreignId('reported_user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->string('reason');

            $table->text('details')
                ->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('community_reports');
    }
};