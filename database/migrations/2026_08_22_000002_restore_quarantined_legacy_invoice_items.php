<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('request_order_relation_quarantine')
            || !Schema::hasTable('invoice_items')
            || !Schema::hasTable('invoices')) {
            return;
        }

        DB::table('request_order_relation_quarantine')
            ->where('source_table', 'invoice_items')
            ->orderBy('source_id')
            ->get()
            ->each(function ($quarantine) {
                $payload = json_decode($quarantine->payload, true);
                $invoiceId = $payload['invoice_id'] ?? null;
                if (!$invoiceId || !DB::table('invoices')->where('id', $invoiceId)->exists()) return;

                $alreadyRestored = DB::table('invoice_items')
                    ->where('invoice_id', $invoiceId)
                    ->where('item_type', $payload['item_type'] ?? 'TR')
                    ->where('hpp', $payload['hpp'] ?? 0)
                    ->where('jual', $payload['jual'] ?? 0)
                    ->exists();
                if ($alreadyRestored) return;

                $type = ($payload['item_type'] ?? 'TR') === 'NTR' ? 'NTR' : 'TR';
                DB::table('invoice_items')->insert([
                    'invoice_id' => $invoiceId,
                    // ID Request/DO lama sengaja tidak dipakai karena sudah terbukti stale/orphan.
                    'request_order_id' => null,
                    'delivery_order_id' => null,
                    'item_type' => $type,
                    'item_name' => $type === 'TR' ? 'Trucking' : 'Non-Trucking',
                    'description' => $payload['description'] ?: 'Rincian invoice historis',
                    'truck_type' => null,
                    'quantity' => 1,
                    'unit_price' => $payload['jual'] ?? 0,
                    'hpp' => $payload['hpp'] ?? 0,
                    'jual' => $payload['jual'] ?? 0,
                    'created_at' => $payload['created_at'] ?? now(),
                    'updated_at' => now(),
                ]);
            });
    }

    public function down(): void
    {
        if (!Schema::hasTable('invoice_items')) return;

        DB::table('invoice_items')
            ->whereNull('request_order_id')
            ->whereNull('delivery_order_id')
            ->where('description', 'Rincian invoice historis')
            ->delete();
    }
};
