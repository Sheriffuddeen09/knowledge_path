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
       Schema::create('chat_reports', function (Blueprint $table) {
    $table->id();
    $table->foreignId('chat_id')->constrained()->cascadeOnDelete();
    $table->foreignId('reporter_id')->constrained('users')->cascadeOnDelete();
    $table->foreignId('reported_user_id')->constrained('users')->cascadeOnDelete();
    $table->string('reason');
    $table->text('details')->nullable();
    $table->timestamps();

    $table->unique(['chat_id', 'reporter_id']);
    });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('chat_reports');
    }
};
