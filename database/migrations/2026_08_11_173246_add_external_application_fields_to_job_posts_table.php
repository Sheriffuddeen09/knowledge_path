<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('job_posts', function (Blueprint $table) {

            $table->boolean('apply_on_website')
                ->default(false)
                ->after('payment_required');

            $table->string('application_website')
                ->nullable()
                ->after('apply_on_website');

        });
    }

    public function down(): void
    {
        Schema::table('job_posts', function (Blueprint $table) {

            $table->dropColumn([
                'apply_on_website',
                'application_website',
            ]);

        });
    }
};