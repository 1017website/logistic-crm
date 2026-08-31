<?php

namespace App\Services;

use App\Models\DeliveryOrder;
use App\Models\InvoiceItem;
use App\Models\RequestOrder;
use Illuminate\Support\Collection;

class InvoiceBillingService
{
    /** Catatan default timeline saat tahap alur DO dikoreksi otomatis. */
    private const FLOW_NOTES = [
        'closed'   => 'Tidak ada invoice terbit yang tersisa. DO dibuka kembali untuk ditagih.',
        'invoiced' => 'Invoice terbit untuk komponen DO ini.',
        'paid'     => 'Seluruh komponen DO sudah lunas.',
    ];

    /**
     * Sinkronkan status tagihan DO final dan Request DO setelah invoice berubah.
     *
     * @param string|null $reason Catatan timeline bila tahap alur DO ikut dikoreksi.
     */
    public function sync(iterable $doIds, ?string $reason = null): void
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
            $this->reconcileFlowStatus($do, $status, $items, $reason);
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

    /**
     * Selaraskan tahap alur DO final dengan kondisi invoice yang sebenarnya.
     *
     * Hanya ekor alur penagihan (closed/invoiced/paid) yang dikoreksi; DO yang
     * masih berjalan di lapangan tidak pernah diubah dari sini. Ini menutup
     * kasus invoice dikembalikan ke draft atau dihapus, yang sebelumnya
     * meninggalkan DO pada tahap "Invoice Terbit" sehingga tidak dapat
     * dipilih lagi untuk ditagih.
     */
    private function reconcileFlowStatus(
        DeliveryOrder $do,
        string $invoiceStatus,
        Collection $billedItems,
        ?string $reason
    ): void {
        if (!in_array($do->status, ['closed', 'invoiced', 'paid'], true)) {
            return;
        }

        // Draft invoice memakai komponen DO, tetapi belum menerbitkan tagihan.
        $hasIssuedInvoice = $billedItems->contains(
            fn(InvoiceItem $item) => in_array($item->invoice?->status, ['invoice', 'termin', 'paid'], true)
        );

        $target = match (true) {
            $invoiceStatus === 'paid' => 'paid',
            $hasIssuedInvoice         => 'invoiced',
            default                   => 'closed',
        };

        if ($target === $do->status) {
            return;
        }

        $do->transition($target, $reason ?: self::FLOW_NOTES[$target], auth()->id());
    }
}
