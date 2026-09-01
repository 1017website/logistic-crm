<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('invoices') || Schema::hasColumn('invoices', 'periode_invoice')) {
            return;
        }

        Schema::table('invoices', function (Blueprint $table) {
            $table->date('periode_invoice')->nullable()->after('tgl_buat')
                ->comment('Bulan pencatatan laporan; independen dari tanggal invoice dan TOP');
            $table->index('periode_invoice', 'invoice_period_idx');
        });

        DB::table('invoices')
            ->select(['id', 'tgl_buat', 'created_at'])
            ->orderBy('id')
            ->chunkById(500, function ($invoices) {
                foreach ($invoices as $invoice) {
                    $sourceDate = $invoice->tgl_buat ?: $invoice->created_at;
                    if (!$sourceDate) {
                        continue;
                    }

                    DB::table('invoices')->where('id', $invoice->id)->update([
                        'periode_invoice' => Carbon::parse($sourceDate)->startOfMonth()->toDateString(),
                    ]);
                }
            });
    }

    public function down(): void
    {
        if (!Schema::hasTable('invoices') || !Schema::hasColumn('invoices', 'periode_invoice')) {
            return;
        }

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropIndex('invoice_period_idx');
            $table->dropColumn('periode_invoice');
        });
    }
};
