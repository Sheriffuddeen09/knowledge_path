<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('job_interviews', function (Blueprint $table) {

            $table->text('call_link')
                ->nullable()
                ->after('meeting_link');

        });
    }

    public function down(): void
    {
        Schema::table('job_interviews', function (Blueprint $table) {

            $table->dropColumn('call_link');

        });
    }
};