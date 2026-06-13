<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (
            Schema::hasTable('community_members') &&
            !Schema::hasColumn(
                'community_members',
                'membership_status'
            )
        ) {

            Schema::table(
                'community_members',
                function (Blueprint $table) {

                    $table->enum(
                        'membership_status',
                        [
                            'active',
                            'left',
                            'removed',
                        ]
                    )
                    ->default('active')
                    ->after('muted');

                }
            );
        }
    }

    public function down(): void
    {
        if (
            Schema::hasTable('community_members') &&
            Schema::hasColumn(
                'community_members',
                'membership_status'
            )
        ) {

            Schema::table(
                'community_members',
                function (Blueprint $table) {

                    $table->dropColumn(
                        'membership_status'
                    );

                }
            );
        }
    }
};