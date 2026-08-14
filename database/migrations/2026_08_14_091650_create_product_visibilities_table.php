<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_visibilities', function (Blueprint $table) {

            $table->id();

            $table->foreignId('product_id')
                ->unique()
                ->constrained('products')
                ->cascadeOnDelete();

            /*
            |--------------------------------------------------------------------------
            | 25 = 1/4
            | 50 = 1/2
            | 75 = 3/4
            | 100 = all
            |--------------------------------------------------------------------------
            */

            $table->unsignedTinyInteger('audience')
                ->default(0);

            $table->unsignedInteger('required_badges')
                ->default(0);

            $table->boolean('visibility_unlocked')
                ->default(false);

            $table->timestamp('unlocked_at')
                ->nullable();

            $table->timestamps();

        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_visibilities');
    }
};