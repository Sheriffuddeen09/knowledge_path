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
    Schema::create('reply_reactions', function (Blueprint $table) {
        $table->id();
        $table->foreignId('reply_id')->constrained('comments')->onDelete('cascade'); // assuming replies are comments with parent_id
        $table->foreignId('user_id')->nullable()->constrained()->onDelete('cascade');
        $table->string('emoji');
        $table->timestamps();
    });
}


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reply_reactions');
    }
};
