<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseOrderItem extends Model
{
    protected $fillable = [
        'purchase_order_id','product_name','unit','qty',
        'buy_price','sell_price','description'
    ];

    protected $casts = [
        'qty'        => 'decimal:3',
        'buy_price'  => 'decimal:0',
        'sell_price' => 'decimal:0',
    ];

    public function purchaseOrder(): BelongsTo { return $this->belongsTo(PurchaseOrder::class); }

    public function getSubtotalRevenueAttribute(): float { return $this->qty * $this->sell_price; }
    public function getSubtotalCostAttribute(): float    { return $this->qty * $this->buy_price; }
    public function getGrossProfitAttribute(): float     { return $this->subtotal_revenue - $this->subtotal_cost; }
}
