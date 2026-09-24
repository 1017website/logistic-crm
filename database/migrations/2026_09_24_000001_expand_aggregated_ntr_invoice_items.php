<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pecah baris NTR lama hanya ketika nilainya masih sama persis dengan
     * rincian sumber. Invoice yang pernah dikoreksi manual tidak disentuh.
     */
    public function up(): void
    {
        if (! Schema::hasTable('invoice_items')
            || ! Schema::hasTable('delivery_orders')
            || ! Schema::hasTable('order_job_details')) {
            return;
        }

        DB::table('invoice_items')
            ->where('item_type', 'NTR')
            ->whereNotNull('delivery_order_id')
            ->orderBy('id')
            ->get()
            ->each(function (object $item): void {
                $sameComponentCount = DB::table('invoice_items')
                    ->where('invoice_id', $item->invoice_id)
                    ->where('delivery_order_id', $item->delivery_order_id)
                    ->where('item_type', 'NTR')
                    ->count();
                if ($sameComponentCount !== 1) {
                    return;
                }

                $requestOrderId = $item->request_order_id ?: DB::table('delivery_orders')
                    ->where('id', $item->delivery_order_id)
                    ->value('request_order_id');
                if (! $requestOrderId) {
                    return;
                }

                $jobLines = DB::table('order_job_details')
                    ->where('request_order_id', $requestOrderId)
                    ->where(function ($query) {
                        $query->whereRaw("UPPER(TRIM(COALESCE(job_code, ''))) = 'NTR'")
                            ->orWhere(function ($legacy) {
                                $legacy->whereRaw("UPPER(TRIM(COALESCE(job_code, ''))) <> 'TR'")
                                    ->whereRaw("LOWER(COALESCE(job_name, '')) NOT LIKE '%truck%'");
                            });
                    })
                    ->where(function ($query) {
                        $query->where('riil_biaya', '>', 0)->orWhere('riil_jual', '>', 0);
                    })
                    ->orderBy('id')
                    ->get()
                    ->map(fn (object $detail) => [
                        'item_name' => mb_substr(trim((string) $detail->job_name) ?: 'Non-Trucking', 0, 255),
                        'description' => mb_substr(
                            trim((string) $detail->catatan)
                                ?: (trim((string) $detail->job_name) ?: 'Non-Trucking'),
                            0,
                            255
                        ),
                        'quantity' => 1,
                        'unit_price' => (int) $detail->riil_jual,
                        'hpp' => (int) $detail->riil_biaya,
                        'jual' => (int) $detail->riil_jual,
                    ]);

                $lines = $jobLines;
                if ($lines->isEmpty() && Schema::hasTable('request_order_items')) {
                    $lines = DB::table('request_order_items')
                        ->where('request_order_id', $requestOrderId)
                        ->where('service_type', 'NTR')
                        ->where(function ($query) {
                            $query->where('buy_price', '>', 0)->orWhere('sell_price', '>', 0);
                        })
                        ->orderBy('id')
                        ->get()
                        ->map(function (object $source) {
                            $quantity = max(0.001, (float) $source->qty);
                            $name = mb_substr(trim((string) $source->service_name) ?: 'Non-Trucking', 0, 255);

                            return [
                                'item_name' => $name,
                                'description' => mb_substr(trim((string) $source->description) ?: $name, 0, 255),
                                'quantity' => $quantity,
                                'unit_price' => (int) $source->sell_price,
                                'hpp' => (int) round($quantity * (float) $source->buy_price),
                                'jual' => (int) round($quantity * (float) $source->sell_price),
                            ];
                        });
                }

                if ($lines->count() < 2
                    || (int) $lines->sum('hpp') !== (int) $item->hpp
                    || (int) $lines->sum('jual') !== (int) $item->jual) {
                    return;
                }

                $base = [
                    'invoice_id' => $item->invoice_id,
                    'request_order_id' => $requestOrderId,
                    'delivery_order_id' => $item->delivery_order_id,
                    'item_type' => 'NTR',
                    'truck_type' => $item->truck_type,
                    'created_at' => $item->created_at,
                    'updated_at' => now(),
                ];
                $first = $lines->shift();
                DB::table('invoice_items')->where('id', $item->id)->update([
                    ...$first,
                    'updated_at' => now(),
                ]);
                foreach ($lines as $line) {
                    DB::table('invoice_items')->insert([...$base, ...$line]);
                }
            });
    }

    public function down(): void
    {
        // Rincian yang sudah menjadi bagian dokumen tagihan tidak digabung ulang.
    }
};
