<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * DELIVERY ORDER (DO final) — terbit otomatis saat Approval Penugasan
 * disetujui. Membawa alur lapangan:
 *   surat_jalan -> pickup -> in_delivery -> pod -> verifikasi_pod
 *   -> closed -> invoiced -> paid
 */
class DeliveryOrder extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'do_number', 'request_order_id', 'customer_id', 'vendor_id', 'user_id',
        'status', 'assignment_type', 'fleet_info', 'driver_name', 'driver_phone',
        'origin', 'destination', 'do_date', 'pickup_date', 'delivery_date',
        'surat_jalan_file', 'pod_file', 'pod_at', 'pod_verified_by', 'pod_verified_at',
        'actual_cost', 'other_cost', 'closed_by', 'closed_at', 'notes',
    ];

    protected $casts = [
        'do_date'         => 'date',
        'pickup_date'     => 'date',
        'delivery_date'   => 'date',
        'pod_at'          => 'datetime',
        'pod_verified_at' => 'datetime',
        'closed_at'       => 'datetime',
        'actual_cost'     => 'decimal:0',
        'other_cost'      => 'decimal:0',
    ];

    public const FLOW = [
        'surat_jalan'    => 'Surat Jalan',
        'pickup'         => 'Pickup',
        'in_delivery'    => 'Delivery',
        'pod'            => 'POD Diterima',
        'verifikasi_pod' => 'Verifikasi POD',
        'closed'         => 'DO Ditutup',
        'invoiced'       => 'Invoice Terbit',
        'paid'           => 'Lunas',
        'cancelled'      => 'Dibatalkan',
    ];

    public const NEXT = [
        'surat_jalan'    => 'pickup',
        'pickup'         => 'in_delivery',
        'in_delivery'    => 'pod',
        'pod'            => 'verifikasi_pod',
        'verifikasi_pod' => 'closed',
        'closed'         => 'invoiced',
        'invoiced'       => 'paid',
    ];

    public function requestOrder(): BelongsTo { return $this->belongsTo(RequestOrder::class); }
    public function customer(): BelongsTo     { return $this->belongsTo(Customer::class); }
    public function vendor(): BelongsTo       { return $this->belongsTo(Vendor::class); }
    public function salesUser(): BelongsTo    { return $this->belongsTo(User::class, 'user_id'); }
    public function podVerifier(): BelongsTo  { return $this->belongsTo(User::class, 'pod_verified_by'); }
    public function closer(): BelongsTo       { return $this->belongsTo(User::class, 'closed_by'); }

    public function statusLogs(): MorphMany
    {
        return $this->morphMany(OrderStatusLog::class, 'loggable')->latest();
    }

    public function getItemsAttribute()
    {
        return $this->requestOrder?->items ?? collect();
    }

    public function getTotalRevenueAttribute(): float
    {
        return $this->requestOrder?->total_revenue ?? 0;
    }

    public function getTotalCostAttribute(): float
    {
        $actual = (float) $this->actual_cost + (float) $this->other_cost;
        return $actual > 0 ? $actual : ($this->requestOrder?->total_cost ?? 0);
    }

    public function getGrossProfitAttribute(): float
    {
        return $this->total_revenue - $this->total_cost;
    }

    public function getFlowLabelAttribute(): string
    {
        return self::FLOW[$this->status] ?? ucfirst((string) $this->status);
    }

    public function getFlowColorAttribute(): string
    {
        return match($this->status) {
            'surat_jalan'           => 'secondary',
            'pickup'                => 'primary',
            'in_delivery'           => 'warning',
            'pod', 'verifikasi_pod' => 'purple',
            'closed'                => 'indigo',
            'invoiced'              => 'warning',
            'paid'                  => 'success',
            'cancelled'             => 'danger',
            default                 => 'secondary',
        };
    }

    public static function generateDoNumber(): string
    {
        $prefix = 'DO-' . date('Ym') . '-';
        $last   = static::withTrashed()->where('do_number', 'like', $prefix . '%')
            ->orderByDesc('do_number')->value('do_number');
        $seq    = $last ? (intval(substr($last, -4)) + 1) : 1;
        return $prefix . str_pad($seq, 4, '0', STR_PAD_LEFT);
    }

    public function transition(string $to, ?string $note = null, ?int $userId = null): void
    {
        $from = $this->status;
        $this->update(['status' => $to]);
        OrderStatusLog::record($this, $from, $to, $userId, $note);
    }
}
