<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Menambah role baru: Sales Admin, Transport Planner, Finance.
 *
 * Kolom `role` pada tabel users diasumsikan VARCHAR (bukan ENUM ketat)
 * sehingga penambahan nilai role cukup tanpa ALTER ENUM. Migration ini
 * memastikan kolom bertipe string yang cukup panjang. Idempotent.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('users')) {
            return;
        }

        // Pastikan kolom role mampu menampung label role baru.
        // Jika sebelumnya ENUM, ubah menjadi VARCHAR agar fleksibel.
        try {
            DB::statement("ALTER TABLE `users` MODIFY `role` VARCHAR(50) NOT NULL DEFAULT 'Sales Executive'");
        } catch (\Throwable $e) {
            // Abaikan bila driver tidak mendukung / sudah sesuai.
        }
    }

    public function down(): void
    {
        // Tidak menurunkan role agar data role baru tidak rusak.
    }
};
