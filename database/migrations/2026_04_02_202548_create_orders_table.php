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
        Schema::create('orders', function (Blueprint $table) {
    $table->id();

    $table->foreignId('user_id')->constrained()->onDelete('cascade');

    // USER INFO (IMPORTANT)
    $table->string('first_name');
    $table->string('last_name');
    $table->string('email');
    $table->string('phone');

    // ADDRESS
    $table->string('address')->nullable();
    $table->string('city')->nullable();
    $table->string('state')->nullable();
    $table->string('zip')->nullable();

    // PAYMENT
    $table->string('payment_method');

    // TOTALS
    $table->decimal('subtotal', 10, 2);
    $table->decimal('delivery_price', 10, 2)->default(0);
    $table->decimal('discount', 10, 2)->default(0);
    $table->decimal('total_price', 10, 2);

    // STATUS
    $table->enum('status', ['pending', 'active', 'cancelled'])->default();

    $table->timestamps();

    $table->string('order_token')->nullable();
    $table->string('order_hash')->nullable();
    $table->unique('order_token');
    $table->unique('order_hash');
});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
