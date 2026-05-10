<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeliveryOrder extends Model
{
    protected $fillable = [
        'do_number','customer_id','vendor_id','lead_id',
        'service_type','route','amount','cost','other_cost',
        'currency','status','order_date'
    ];

    protected $casts = [
        'order_date'  => 'date',
        'amount'      => 'decimal:0',
        'cost'        => 'decimal:0',
        'other_cost'  => 'decimal:0',
    ];

    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }
    public function vendor(): BelongsTo   { return $this->belongsTo(Vendor::class); }
    public function lead(): BelongsTo     { return $this->belongsTo(Lead::class); }

    /** Total biaya (vendor cost + other cost) */
    public function getTotalCostAttribute(): float
    {
        return (float) $this->cost + (float) $this->other_cost;
    }

    /** Gross Profit = Revenue - Cost Vendor */
    public function getGrossProfitAttribute(): float
    {
        return (float) $this->amount - (float) $this->cost;
    }

    /** Nett Profit = Revenue - Total Cost */
    public function getNettProfitAttribute(): float
    {
        return (float) $this->amount - $this->total_cost;
    }

    /** Gross Margin % */
    public function getGrossMarginAttribute(): float
    {
        if ((float) $this->amount == 0) return 0;
        return round(($this->gross_profit / (float) $this->amount) * 100, 1);
    }

    /** Nett Margin % */
    public function getNettMarginAttribute(): float
    {
        if ((float) $this->amount == 0) return 0;
        return round(($this->nett_profit / (float) $this->amount) * 100, 1);
    }
}

