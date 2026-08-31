<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * TOP (term of payment) per customer dalam hari.
     *
     * Null berarti mengikuti default global (setting invoice_default_top_days),
     * bukan berarti tanpa tempo — supaya customer yang belum diisi tetap punya
     * due date dan terhitung menua di laporan piutang.
     */
    public function up(): void
    {
        if (!Schema::hasTable('customers') || Schema::hasColumn('customers', 'top_days')) {
            return;
        }

        Schema::table('customers', function (Blueprint $table): void {
            $table->unsignedSmallInteger('top_days')->nullable()->after('invoice_code');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('customers') || !Schema::hasColumn('customers', 'top_days')) {
            return;
        }

        Schema::table('customers', function (Blueprint $table): void {
            $table->dropColumn('top_days');
        });
    }
};
