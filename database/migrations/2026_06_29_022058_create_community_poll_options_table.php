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
        Schema::create('community_poll_options', function (Blueprint $table) {

                $table->id();

                $table->foreignId('poll_id')
                    ->constrained('community_polls')
                    ->cascadeOnDelete();

                $table->string('option');

                $table->unsignedInteger('votes')
                    ->default(0);

                $table->timestamps();

            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('community_poll_options');
    }
};
