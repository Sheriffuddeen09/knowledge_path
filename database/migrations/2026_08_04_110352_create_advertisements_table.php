<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration
{
public function up(): void
{
Schema::create('advertisements', function (Blueprint $table) {
$table->id();
$table->foreignId("user_id")
->constrained()
->cascadeOnDelete();

$table->enum("type", [ "advertisement", "sponsorship"
]);

$table->string("title");

$table->longText("description");

$table->string("link")
->nullable();

$table->string("media")
->nullable();

$table->enum("media_type", [ "image", "video"
])->nullable();

$table->enum("audience", [ "25", "50", "75", "100"
])->nullable("25");

$table->unsignedInteger("required_badges")
->default(0);

$table->enum("status", [ "pending", "approved", "declined"
])->default("pending");
$table->boolean("visibility_unlocked")
->default(false);

$table->timestamp("approved_at")
->nullable();
$table->timestamps();
});
}
public function down(): void
{
Schema::dropIfExists('advertisements');
}
};
