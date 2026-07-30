<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceItem extends Model
{
    protected $fillable = [
        'invoice_id', 'request_order_id', 'delivery_order_id',
        'item_type', 'description', 'hpp', 'jual',
    ];

    protected $casts = ['hpp' => 'decimal:0', 'jual' => 'decimal:0'];

    public function invoice(): BelongsTo      { return $this->belongsTo(Invoice::class); }
    public function requestOrder(): BelongsTo { return $this->belongsTo(RequestOrder::class); }
    public function deliveryOrder(): BelongsTo { return $this->belongsTo(DeliveryOrder::class); }
}
