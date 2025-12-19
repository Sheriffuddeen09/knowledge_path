<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // This runs when you do `php artisan migrate`
        DB::table('messages')
            ->where('type', 'text')
            ->whereNull('message')
            ->update([
                'message' => DB::raw('COALESCE(file, "")') // copy old text from file if exists
            ]);
    }

    public function down(): void
    {
        // This runs when you do `php artisan migrate:rollback`
        DB::table('messages')
            ->where('type', 'text')
            ->where('message', '')
            ->update(['message' => null]);
    }
};
