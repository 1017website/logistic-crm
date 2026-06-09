<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DeliveryOrder extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'do_number', 'customer_id', 'vendor_id', 'lead_id', 'user_id',
        'currency', 'status', 'order_date', 'notes',
        'delivery_type', 'origin', 'destination',
        'tracking_number', 'estimated_arrival',
    ];

    protected $casts = [
        'order_date'        => 'date',
        'estimated_arrival' => 'date',
    ];

    public const DELIVERY_TYPES = [
        'Land Freight',
        'Sea Freight',
        'Air Freight',
        'Pengiriman Kilat & Instan',
    ];

    public function customer(): BelongsTo  { return $this->belongsTo(Customer::class); }
    public function vendor(): BelongsTo    { return $this->belongsTo(Vendor::class); }
    public function lead(): BelongsTo      { return $this->belongsTo(Lead::class); }
    public function salesUser(): BelongsTo { return $this->belongsTo(User::class, 'user_id'); }
    public function items(): HasMany       { return $this->hasMany(DeliveryOrderItem::class); }

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

    public function getGrossProfitAttribute(): float
    {
        return $this->total_revenue - $this->total_cost;
    }

    public function getGrossMarginAttribute(): float
    {
        if ($this->total_revenue == 0) return 0;
        return round(($this->gross_profit / $this->total_revenue) * 100, 1);
    }

    public static function generateDoNumber(): string
    {
        $prefix = 'DO-' . date('Ym') . '-';
        $last   = static::withTrashed()->where('do_number', 'like', $prefix . '%')
            ->orderByDesc('do_number')->value('do_number');
        $seq    = $last ? (intval(substr($last, -4)) + 1) : 1;
        return $prefix . str_pad($seq, 4, '0', STR_PAD_LEFT);
    }
}
