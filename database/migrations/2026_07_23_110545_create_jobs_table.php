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
       
    Schema::create('jobs', function (Blueprint $table) {

        $table->id();

        $table->foreignId('user_id')
        ->constrained()
        ->cascadeOnDelete();

        $table->foreignId('job_category_id')
        ->constrained()
        ->cascadeOnDelete();

        $table->string('title');

        $table->enum('job_type',[
            'remote',
            'onsite'
        ]);

        $table->string('location')->nullable();

        $table->decimal('payment',12,2);
        $table->string('currency')->default('NGN');


        $table->date('expire_date');

        $table->text('objective');

        $table->longText('description');

        $table->boolean('active')
        ->default(true);

        $table->timestamps();

    });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('jobs');
    }
};
