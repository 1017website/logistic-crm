<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('invoices')) {
            return;
        }

        Schema::table('invoices', function (Blueprint $table) {
            if (!Schema::hasColumn('invoices', 'submitted_at')) {
                $table->dateTime('submitted_at')->nullable()->after('periode_invoice')
                    ->comment('Waktu invoice resmi diterbitkan; dasar TOP otomatis');
            }
            if (!Schema::hasColumn('invoices', 'tgl_tempo_manual')) {
                $table->boolean('tgl_tempo_manual')->default(false)->after('tgl_tempo')
                    ->comment('Tanggal tempo dipilih manual dan tidak dihitung ulang saat submit');
            }
        });

        DB::table('invoices')
            ->whereIn('status', ['invoice', 'termin', 'paid'])
            ->whereNull('submitted_at')
            ->update(['submitted_at' => DB::raw('tgl_buat')]);
    }

    public function down(): void
    {
        if (!Schema::hasTable('invoices')) {
            return;
        }

        Schema::table('invoices', function (Blueprint $table) {
            $columns = [];
            if (Schema::hasColumn('invoices', 'submitted_at')) {
                $columns[] = 'submitted_at';
            }
            if (Schema::hasColumn('invoices', 'tgl_tempo_manual')) {
                $columns[] = 'tgl_tempo_manual';
            }
            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
