<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    
    public function up(): void
{
    Schema::create('products', function (Blueprint $table) {

    $table->id();

    $table->foreignId('category_id')->constrained()->cascadeOnDelete();

    // Basic info
    $table->string('title');
    $table->string('author')->nullable();
    $table->text('description')->nullable();

    // Pricing
    $table->decimal('price',10,2);
    $table->decimal('discount',10,2)->default(0);
    $table->decimal('charges',10,2)->default(0);
    $table->decimal('total_price',10,2)->nullable();

    // Currency
    $table->string('currency')->default('USD');

    // Stock
    $table->integer('stock')->default(0);
    $table->integer('quantity')->default(1);

    // Product attributes
    $table->string('color')->nullable();
    $table->string('size')->nullable();
    $table->decimal('weight',8,2)->nullable();

    // Brand / Company
    $table->string('brand_name')->nullable();
    $table->string('company_name')->nullable();
    $table->boolean('company_available')->default(true);

    // Sale type
    $table->enum('sale_type',['company','individual'])->default('individual');

    // Location
    $table->string('location')->nullable();

    // Delivery
    $table->string('delivery_method')->nullable(); // courier, pickup, shipping
    $table->string('delivery_time_ratio')->nullable(); // 1-2 days, 3-5 days

    // Reviews
    $table->integer('review_total')->default(0);

    // Images
    $table->string('front_image')->nullable();
    $table->string('back_image')->nullable();
    $table->string('side_image')->nullable();

    // Digital products
    $table->string('pdf_file')->nullable();
    $table->boolean('is_digital')->default(false);

    $table->timestamps();
});
}
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
