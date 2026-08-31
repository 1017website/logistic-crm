<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Penanda koreksi harga: dibuka Sales Manager lewat Unapprove harga ketika
     * DO final sudah terbit, agar Finance dapat memperbaiki rincian harga yang
     * salah tanpa melonggarkan aturan beku harga secara umum.
     */
    public function up(): void
    {
        if (!Schema::hasTable('request_orders') || Schema::hasColumn('request_orders', 'price_correction_open')) {
            return;
        }

        Schema::table('request_orders', function (Blueprint $table): void {
            $table->boolean('price_correction_open')->default(false)->after('do_approved');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('request_orders') || !Schema::hasColumn('request_orders', 'price_correction_open')) {
            return;
        }

        Schema::table('request_orders', function (Blueprint $table): void {
            $table->dropColumn('price_correction_open');
        });
    }
};
