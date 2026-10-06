<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use Illuminate\Support\Collection;

class InvoicePrintService
{
    public function nonTruckingGroups(Invoice $invoice, Collection $items): Collection
    {
        $key = static fn (InvoiceItem $item) => $item->delivery_order_id
            ? 'do:'.$item->delivery_order_id
            : ($item->request_order_id ? 'request:'.$item->request_order_id : 'item:'.$item->id);
        $groups = $items->groupBy($key);
        $trItems = $invoice->items->where('item_type', 'TR');

        if ($trItems->isEmpty()) {
            $candidates = Invoice::with(['items' => fn ($query) => $query->orderBy('id'),
                'items.requestOrder', 'items.deliveryOrder.requestOrder'])
                ->where('customer_id', $invoice->customer_id)
                ->where('jenis', 'TR')
                ->whereDate('tgl_buat', $invoice->tgl_buat)
                ->where('periode_invoice', $invoice->periode_invoice)
                ->get()
                ->filter(fn (Invoice $candidate) => $groups->keys()->diff($candidate->items->map($key))->isEmpty())
                ->sortBy(fn (Invoice $candidate) => [
                    $candidate->items->map($key)->unique()->count() - $groups->count(),
                    abs($candidate->id - $invoice->id),
                    $candidate->id,
                ]);
            $trItems = $candidates->first()?->items ?? collect();
        }

        $headers = $trItems->unique($key)->keyBy($key);
        foreach ($groups as $groupKey => $lines) {
            if (! $headers->has($groupKey)) {
                $headers->put($groupKey, $lines->first());
            }
        }

        return $headers->map(fn (InvoiceItem $header, string $groupKey) => [
            'header' => $header,
            'items' => $groups->get($groupKey, collect())->values(),
        ])->values();
    }
}
