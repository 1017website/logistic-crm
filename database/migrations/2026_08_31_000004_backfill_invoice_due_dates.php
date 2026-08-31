<?php

use App\Models\Customer;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Isi due date invoice terbit yang masih kosong memakai TOP customer
     * (kolom customers.top_days), atau default global bila belum diisi.
     *
     * Tanpa due date, invoice tidak pernah terhitung menua di Laporan
     * Outstanding maupun kolom umur di Laporan Invoice, sehingga piutang lewat
     * tempo tidak terlihat. Draft sengaja dilewati karena due date-nya
     * ditetapkan saat invoice diterbitkan.
     */
    public function up(): void
    {
        if (!Schema::hasTable('invoices') || !Schema::hasTable('customers')) {
            return;
        }

        $defaultTop = Schema::hasColumn('customers', 'top_days')
            ? Customer::defaultTopDays()
            : Customer::FALLBACK_TOP_DAYS;

        $topPerCustomer = Schema::hasColumn('customers', 'top_days')
            ? DB::table('customers')->pluck('top_days', 'id')
            : collect();

        DB::table('invoices')
            ->whereNull('tgl_tempo')
            ->whereNotNull('tgl_buat')
            ->whereIn('status', ['invoice', 'termin', 'paid'])
            ->orderBy('id')
            ->chunkById(200, function ($invoices) use ($topPerCustomer, $defaultTop): void {
                foreach ($invoices as $invoice) {
                    $top = (int) ($topPerCustomer[$invoice->customer_id] ?? 0);
                    if ($top <= 0) {
                        $top = $defaultTop;
                    }

                    DB::table('invoices')
                        ->where('id', $invoice->id)
                        ->update([
                            'tgl_tempo' => date('Y-m-d', strtotime($invoice->tgl_buat . ' +' . $top . ' days')),
                        ]);
                }
            });
    }

    /** Due date asli tidak dapat direkonstruksi; data dipertahankan. */
    public function down(): void
    {
        // Tidak mengembalikan due date menjadi kosong.
    }
};
