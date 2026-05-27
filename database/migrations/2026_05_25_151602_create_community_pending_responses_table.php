<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {

    public function up(): void
    {
        Schema::create(
            'community_pending_responses',
            function (Blueprint $table) {

                $table->id();

                $table->foreignId(
                    'community_id'
                );

                $table->foreignId(
                    'sender_id'
                );

                $table->longText(
                    'message'
                );

                $table->unsignedBigInteger(
                    'reply_to'
                )->nullable();

                $table->enum(
                    'status',
                    [
                        'pending',
                        'approved',
                        'rejected'
                    ]
                )->default('pending');

                $table->text(
                    'admin_response'
                )->nullable();

                $table->timestamps();
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'community_pending_responses'
        );
    }
};