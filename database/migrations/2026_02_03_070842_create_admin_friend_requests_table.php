<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAdminFriendRequestsTable extends Migration
{
    public function up(): void
    {
        Schema::create('admin_friend_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id');    // requester
            $table->foreignId('admin_id'); // requested

            $table->enum('status', ['pending', 'accepted', 'declined'])->default('pending');

            // visibility flags
            $table->boolean('hidden_for_requester')->default(false);
            $table->boolean('hidden_for_requested')->default(false);

            // temporary removal
            $table->boolean('is_hidden')->default(false);

            $table->timestamps();

            $table->unique(['user_id', 'admin_id']);
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('admin_friend_requests');
    }
}
