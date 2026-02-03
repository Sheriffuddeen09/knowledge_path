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
       Schema::create('post_reports', function (Blueprint $table) {
    $table->id();
    $table->foreignId('post_id')->constrained('posts')->cascadeOnDelete();
    $table->foreignId('reported_user_id')->constrained('users')->cascadeOnDelete();
    $table->foreignId('reporter_id')->constrained('users')->cascadeOnDelete();
    $table->string('reason');
    $table->text('details')->nullable();
    $table->timestamps();

    $table->unique(['post_id', 'reporter_id']);
    });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('post_reports');
    }
};
