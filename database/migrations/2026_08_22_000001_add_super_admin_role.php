<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('users')) return;

        // Pastikan kolom role tidak lagi berupa ENUM ketat pada instalasi lama.
        try {
            DB::statement("ALTER TABLE `users` MODIFY `role` VARCHAR(50) NOT NULL DEFAULT 'Sales Executive'");
        } catch (\Throwable $e) {
            // SQLite/test driver atau database yang kolomnya sudah VARCHAR.
        }

        // Promosikan administrator yang sudah ada agar selalu tersedia approver.
        DB::table('users')->where('role', 'Admin')->update(['role' => 'Super Admin']);
    }

    public function down(): void
    {
        if (Schema::hasTable('users')) {
            DB::table('users')->where('role', 'Super Admin')->update(['role' => 'Admin']);
        }
    }
};
