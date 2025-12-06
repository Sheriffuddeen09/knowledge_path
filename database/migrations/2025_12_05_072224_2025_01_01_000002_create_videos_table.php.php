<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up() {
        Schema::create('videos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade'); // uploader (admin)
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->text('description')->nullable();
            $table->string('video_path'); // storage path
            $table->string('thumbnail')->nullable();
            $table->boolean('is_public')->default(true);
            $table->timestamps();
        });
    }
    public function down() {
        Schema::dropIfExists('videos');
    }
};
