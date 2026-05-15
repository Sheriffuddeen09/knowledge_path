
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {

    public function up(): void
    {
        Schema::table(
            'users',
            function (Blueprint $table) {

                $table->boolean(
                    'passkey_enabled'
                )->default(false);

                $table->string(
                    'passkey_name'
                )->nullable();

                $table->timestamp(
                    'passkey_verified_at'
                )->nullable();
            }
        );
    }

    public function down(): void
    {
        Schema::table(
            'users',
            function (Blueprint $table) {

                $table->dropColumn([
                    'passkey_enabled',
                    'passkey_name',
                    'passkey_verified_at'
                ]);
            }
        );
    }
};