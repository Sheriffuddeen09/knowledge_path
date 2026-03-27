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
        Schema::create('order_items', function (Blueprint $table) {
    $table->id();

    $table->foreignId('order_id')->constrained()->onDelete('cascade');

    $table->foreignId('product_id')->constrained()->onDelete('cascade');

    // SNAPSHOT (VERY IMPORTANT)
    $table->string('title');
    $table->string('description');
    $table->string('image')->nullable();

    $table->integer('quantity');

    $table->decimal('price', 10, 2);
    $table->decimal('delivery_price', 10, 2)->default(0);
    $table->decimal('discount', 10, 2)->default(0);
    $table->decimal('total_price', 10, 2);

    $table->string('delivery_method')->nullable();

    $table->timestamps();
});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
