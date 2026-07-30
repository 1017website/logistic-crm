<?php

namespace App\Services;

use App\Models\DeliveryOrder;
use App\Models\InvoiceItem;
use App\Models\RequestOrder;

class InvoiceBillingService
{
    /**
     * Sinkronkan status tagihan DO final dan Request DO setelah invoice berubah.
     */
    public function sync(iterable $doIds): void
    {
        $ids = collect($doIds)->filter()->unique()->values();
        if ($ids->isEmpty()) {
            return;
        }

        $requestOrderIds = collect();
        $dos = DeliveryOrder::with([
            'requestOrder.jobDetails',
            'requestOrder.items',
            'invoiceItems.invoice',
        ])->whereIn('id', $ids)->get();

        foreach ($dos as $do) {
            $requiredTypes = collect($do->invoiceBreakdown())->keys();
            $items = $do->invoiceItems->filter(fn(InvoiceItem $item) => $item->invoice !== null);
            $billedTypes = $items->pluck('item_type')->filter()->unique();

            if ($billedTypes->isEmpty()) {
                $status = 'uninvoiced';
            } elseif ($requiredTypes->diff($billedTypes)->isNotEmpty()) {
                $status = 'partial';
            } else {
                $allPaid = $items->isNotEmpty()
                    && $items->every(fn(InvoiceItem $item) => $item->invoice?->status === 'paid');
                $status = $allPaid ? 'paid' : 'invoiced';
            }

            $do->update(['invoice_status' => $status]);
            if ($do->request_order_id) {
                $requestOrderIds->push($do->request_order_id);
            }
        }

        foreach ($requestOrderIds->unique() as $requestOrderId) {
            $statuses = DeliveryOrder::where('request_order_id', $requestOrderId)
                ->pluck('invoice_status');

            $requestStatus = match (true) {
                $statuses->isEmpty() || $statuses->every(fn($status) => $status === 'uninvoiced') => 'uninvoiced',
                $statuses->every(fn($status) => $status === 'paid') => 'paid',
                $statuses->every(fn($status) => in_array($status, ['invoiced', 'paid'], true)) => 'invoiced',
                default => 'partial',
            };

            RequestOrder::whereKey($requestOrderId)->update(['invoice_status' => $requestStatus]);
        }
    }
}
