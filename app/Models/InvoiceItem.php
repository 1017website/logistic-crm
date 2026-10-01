<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceItem extends Model
{
    protected $fillable = [
        'invoice_id', 'request_order_id', 'delivery_order_id',
        'item_type', 'item_name', 'description', 'truck_type', 'quantity', 'unit_price', 'hpp', 'jual',
    ];

    protected $casts = [
        'quantity' => 'decimal:3', 'unit_price' => 'decimal:0',
        'hpp' => 'decimal:0', 'jual' => 'decimal:0',
    ];

    public function invoice(): BelongsTo      { return $this->belongsTo(Invoice::class); }
    public function requestOrder(): BelongsTo { return $this->belongsTo(RequestOrder::class); }
    public function deliveryOrder(): BelongsTo { return $this->belongsTo(DeliveryOrder::class); }

    public function exportWorkDescription(): string
    {
        $deliveryOrder = $this->deliveryOrder;
        $requestOrder = $this->requestOrder ?? $deliveryOrder?->requestOrder;

        if (! $requestOrder && ! $deliveryOrder) {
            return (string) $this->description;
        }

        $value = static function (...$values): string {
            foreach ($values as $candidate) {
                $text = trim((string) $candidate);
                if ($text !== '' && $text !== '-') {
                    return $text;
                }
            }

            return '-';
        };

        return sprintf(
            'Depo : %s Tujuan: %s, Komoditas: %s (No. Container: %s, No Seal: %s, No. Pol: %s, Armada: %s)',
            $value($requestOrder?->depo, $requestOrder?->muat, $deliveryOrder?->origin, $requestOrder?->origin),
            $value($requestOrder?->tujuan, $requestOrder?->bongkar, $deliveryOrder?->destination, $requestOrder?->destination),
            $value($requestOrder?->komoditi),
            $value($requestOrder?->no_container),
            $value($requestOrder?->no_seal),
            $value($requestOrder?->no_pol),
            $value($requestOrder?->jenis_truck, $this->truck_type),
        );
    }
}
