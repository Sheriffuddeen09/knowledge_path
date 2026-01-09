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
     Schema::dropIfExists('assignment_results');
 }

 public function down(): void
 {
     Schema::create('assignment_results', function (Blueprint $table) {
         $table->id();
         $table->foreignId('assignment_id');
         $table->foreignId('student_id');
         $table->integer('score');
         $table->integer('total_questions');
         $table->boolean('is_late')->default(false);
         $table->timestamps();
     });
 }

 };