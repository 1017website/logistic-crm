<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Backfill sales_user_id dari user_id untuk activity yang sales_user_id-nya null
        DB::statement('UPDATE activities SET sales_user_id = user_id WHERE sales_user_id IS NULL AND user_id IS NOT NULL');
    }

    public function down(): void {}
};
