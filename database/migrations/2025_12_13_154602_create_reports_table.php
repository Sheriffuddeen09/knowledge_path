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
    Schema::create('reports', function (Blueprint $table) {
        $table->id();
        $table->foreignId('video_id')->nullable()->constrained()->onDelete('cascade');
        $table->foreignId('reporter_id')->constrained('users')->onDelete('cascade');
        $table->foreignId('reported_user_id')->constrained('users')->onDelete('cascade');
        $table->string('category');
        $table->text('description');
        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reports');
    }
};
