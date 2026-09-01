<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\DeliveryOrder;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AutomaticInvoiceDraftService
{
    /**
     * Buat draft invoice otomatis untuk seluruh komponen DO yang belum ditagih.
     * TR dan NTR tetap dibuat sebagai invoice terpisah.
     *
     * @return Collection<int, Invoice>
     */
    public function createForClosedDeliveryOrder(DeliveryOrder $deliveryOrder, ?int $operatorId): Collection
    {
        return DB::transaction(function () use ($deliveryOrder, $operatorId) {
            // Samakan urutan lock dengan pembuatan invoice manual: customer lalu DO.
            $customer = Customer::query()->lockForUpdate()->findOrFail($deliveryOrder->customer_id);
            $do = DeliveryOrder::query()
                ->with(['requestOrder.jobDetails', 'requestOrder.items'])
                ->lockForUpdate()
                ->findOrFail($deliveryOrder->id);

            if ($do->status !== 'closed' || !$do->pod_at || !$do->requestOrder?->do_approved) {
                throw ValidationException::withMessages([
                    'general' => 'Draft invoice otomatis hanya dapat dibuat setelah DO ditutup, POD diterima, dan harga disetujui.',
                ]);
            }

            $usedTypes = InvoiceItem::query()
                ->where('delivery_order_id', $do->id)
                ->lockForUpdate()
                ->pluck('item_type')
                ->filter()
                ->unique();

            $rows = collect($do->invoiceBreakdown())
                ->reject(fn(array $row, string $type) => $usedTypes->contains($type));

            if ($rows->isEmpty()) {
                return collect();
            }

            $created = collect();
            $invoiceDate = $do->closed_at?->copy() ?? now();

            foreach ($rows as $type => $row) {
                $seq = Invoice::nextCustomerSeq($customer->id);
                $invoice = Invoice::create([
                    'invoice_id' => 'TMP-' . Str::uuid(),
                    'invoice_number' => Invoice::buildInvoiceNumber($seq, $customer->invoice_number_code, $invoiceDate),
                    'customer_seq' => $seq,
                    'customer_id' => $customer->id,
                    'status' => 'draft',
                    'tgl_buat' => $invoiceDate->toDateString(),
                    'periode_invoice' => $invoiceDate->copy()->startOfMonth()->toDateString(),
                    // TOP mengikuti pengaturan customer, bukan angka tetap.
                    'tgl_tempo' => $customer->dueDateFrom($invoiceDate),
                    'tgl_tempo_manual' => false,
                    'jenis' => $type,
                    'billing_mode' => 'separate',
                    'operator_id' => $operatorId,
                    'notes' => 'Draft otomatis saat DO ' . $do->do_number . ' ditutup.',
                ]);
                $invoice->update([
                    'invoice_id' => 'IV' . Carbon::parse($invoiceDate)->format('ym')
                        . str_pad((string) $invoice->id, 4, '0', STR_PAD_LEFT),
                ]);

                $invoice->items()->create([
                    'request_order_id' => $do->request_order_id,
                    'delivery_order_id' => $do->id,
                    'item_type' => $type,
                    'item_name' => $type === 'TR' ? 'Trucking' : 'Non-Trucking',
                    'description' => $row['description'],
                    'truck_type' => $do->requestOrder?->jenis_truck,
                    'quantity' => 1,
                    'unit_price' => $row['jual'],
                    'hpp' => $row['hpp'],
                    'jual' => $row['jual'],
                ]);

                $invoice->update([
                    'total_hpp' => $row['hpp'],
                    'total_jual' => $row['jual'],
                    'ppn_persen' => 0,
                    'ppn_nominal' => 0,
                    'grand_total' => $row['jual'],
                ]);
                $created->push($invoice);
            }

            app(InvoiceBillingService::class)->sync([$do->id]);

            return $created;
        }, 3);
    }
}
