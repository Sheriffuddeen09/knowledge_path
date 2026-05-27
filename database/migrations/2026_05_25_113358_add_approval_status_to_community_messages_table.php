<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {

    public function up(): void
    {
        Schema::table(
          'community_messages',
          function (
            Blueprint $table
          ) {

            $table->enum(
              'approval_status',
              [
                'pending',
                'approved',
                'rejected'
              ]
            )
            ->default('approved')
            ->after('response_mode');
        });
    }

    public function down(): void
    {
        Schema::table(
          'community_messages',
          function (
            Blueprint $table
          ) {

            $table->dropColumn(
              'approval_status'
            );
        });
    }
};