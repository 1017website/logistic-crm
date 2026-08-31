<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Samakan nomor DO final lama dengan nomor Request DO sumbernya.
     * Data tanpa relasi Request DO dipertahankan sebagai data legacy.
     */
    public function up(): void
    {
        if (!Schema::hasTable('delivery_orders') || !Schema::hasTable('request_orders')) {
            return;
        }

        DB::table('delivery_orders')
            ->whereNotNull('request_order_id')
            ->select(['id', 'request_order_id', 'do_number'])
            ->orderBy('id')
            ->chunkById(200, function ($deliveryOrders): void {
                foreach ($deliveryOrders as $deliveryOrder) {
                    $requestNumber = DB::table('request_orders')
                        ->where('id', $deliveryOrder->request_order_id)
                        ->value('do_number');

                    if (!$requestNumber || $requestNumber === $deliveryOrder->do_number) {
                        continue;
                    }

                    // Pertahankan integritas unique index jika ada data legacy anomali
                    // yang sudah menggunakan nomor RDO milik order lain.
                    $numberIsUsed = DB::table('delivery_orders')
                        ->where('do_number', $requestNumber)
                        ->where('id', '!=', $deliveryOrder->id)
                        ->exists();

                    if (!$numberIsUsed) {
                        DB::table('delivery_orders')
                            ->where('id', $deliveryOrder->id)
                            ->update(['do_number' => $requestNumber]);
                    }
                }
            });
    }

    /** Nomor lama tidak dapat direkonstruksi dengan aman. */
    public function down(): void
    {
        // Data nomor tetap dipertahankan.
    }
};
