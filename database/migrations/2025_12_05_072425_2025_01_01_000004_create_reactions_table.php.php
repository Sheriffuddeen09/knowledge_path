<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up() {
        Schema::create('reactions', function (Blueprint $table) {
            $table->id();
            $table->string('reactionable_type'); // morph
            $table->unsignedBigInteger('reactionable_id');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('emoji', 10); // small emoji or short code
            $table->timestamps();

            $table->unique(['reactionable_type','reactionable_id','user_id','emoji'], 'reaction_unique');
            $table->index(['reactionable_type','reactionable_id']);
        });
    }
    public function down() {
        Schema::dropIfExists('reactions');
    }
};
