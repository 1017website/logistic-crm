<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeliveryOrderItem extends Model
{
    protected $fillable = [
        'delivery_order_id', 'service_name', 'unit', 'qty', 'tonnage',
        'buy_price', 'sell_price', 'description',
    ];

    protected $casts = [
        'qty'        => 'decimal:3',
        'buy_price'  => 'decimal:0',
        'sell_price' => 'decimal:0',
    ];

    public function deliveryOrder(): BelongsTo { return $this->belongsTo(DeliveryOrder::class); }

    public function getSubtotalRevenueAttribute(): float { return $this->qty * $this->sell_price; }
    public function getSubtotalCostAttribute(): float    { return $this->qty * $this->buy_price; }
    public function getGrossProfitAttribute(): float     { return $this->subtotal_revenue - $this->subtotal_cost; }
}
