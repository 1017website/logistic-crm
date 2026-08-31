<?php

use App\Models\DeliveryOrder;
use App\Services\AutomaticInvoiceDraftService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Buat draft untuk DO lama yang sudah selesai tetapi belum ditagih. */
    public function up(): void
    {
        if (!Schema::hasTable('delivery_orders')
            || !Schema::hasTable('request_orders')
            || !Schema::hasTable('invoices')
            || !Schema::hasTable('invoice_items')) {
            return;
        }

        DeliveryOrder::query()
            ->where('status', 'closed')
            ->whereNotNull('pod_at')
            ->whereHas('requestOrder', fn($query) => $query->where('do_approved', true))
            ->whereDoesntHave('invoiceItems')
            ->orderBy('id')
            ->chunkById(100, function ($deliveryOrders): void {
                foreach ($deliveryOrders as $deliveryOrder) {
                    app(AutomaticInvoiceDraftService::class)
                        ->createForClosedDeliveryOrder($deliveryOrder, $deliveryOrder->closed_by);
                }
            });
    }

    /** Draft yang sudah dibuat dipertahankan agar nomor invoice tidak berubah. */
    public function down(): void
    {
        // Tidak menghapus data invoice.
    }
};
