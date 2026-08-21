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
}
