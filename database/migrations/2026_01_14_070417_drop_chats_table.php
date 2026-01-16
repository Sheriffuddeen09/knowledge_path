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
     Schema::dropIfExists('chats');
 }

 public function down(): void
 {
       Schema::create('chats', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('teacher_id');
        $table->unsignedBigInteger('student_id');
        $table->timestamps();

        $table->unique(['teacher_id', 'student_id']);
    });
 }

 };