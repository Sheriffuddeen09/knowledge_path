<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('job_applications', function (Blueprint $table) {

            $table->timestamp('removed_by_poster_at')
                ->nullable()
                ->after('reviewed_at');

            $table->timestamp('removed_by_applicant_at')
                ->nullable()
                ->after('removed_by_poster_at');

        });
    }

    public function down(): void
    {
        Schema::table('job_applications', function (Blueprint $table) {

            $table->dropColumn([
                'removed_by_poster_at',
                'removed_by_applicant_at',
            ]);

        });
    }
};