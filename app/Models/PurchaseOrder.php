<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseOrder extends Model
{
    use SoftDeletes;
    protected $fillable = [
        'po_number','customer_id','supplier_id','lead_id','user_id',
        'currency','status','order_date','notes'
    ];

    protected $casts = ['order_date' => 'date'];

    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }
    public function supplier(): BelongsTo { return $this->belongsTo(Supplier::class); }
    public function lead(): BelongsTo     { return $this->belongsTo(Lead::class); }
    public function salesUser(): BelongsTo { return $this->belongsTo(\App\Models\User::class, 'user_id'); }
    public function items(): HasMany      { return $this->hasMany(PurchaseOrderItem::class); }

    /** Total Revenue = SUM(qty × sell_price) */
    public function getTotalRevenueAttribute(): float
    {
        return $this->items->sum(fn($i) => $i->qty * $i->sell_price);
    }

    /** Total HPP = SUM(qty × buy_price) */
    public function getTotalCostAttribute(): float
    {
        return $this->items->sum(fn($i) => $i->qty * $i->buy_price);
    }

    /** Gross Profit = Revenue - HPP */
    public function getGrossProfitAttribute(): float
    {
        return $this->total_revenue - $this->total_cost;
    }

    /** Gross Margin % */
    public function getGrossMarginAttribute(): float
    {
        if ($this->total_revenue == 0) return 0;
        return round(($this->gross_profit / $this->total_revenue) * 100, 1);
    }

    public static function generatePoNumber(): string
    {
        $prefix = 'PO-' . date('Ym') . '-';
        $last   = static::where('po_number', 'like', $prefix . '%')
            ->orderByDesc('po_number')->value('po_number');
        $seq    = $last ? (intval(substr($last, -4)) + 1) : 1;
        return $prefix . str_pad($seq, 4, '0', STR_PAD_LEFT);
    }
}
