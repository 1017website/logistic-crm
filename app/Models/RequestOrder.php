<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * REQUEST DO — order yang dibuat Sales, diverifikasi Sales Admin,
 * lalu ditugaskan oleh Transport Planner & disetujui (Approval Penugasan).
 * Setelah approval, otomatis menghasilkan DeliveryOrder (DO final).
 *
 * Tabel: request_orders (hasil rename dari delivery_orders lama).
 */
class RequestOrder extends Model
{
    use SoftDeletes;

    protected $table = 'request_orders';

    protected $fillable = [
        'do_number', 'customer_id', 'vendor_id', 'lead_id', 'user_id',
        'currency', 'status', 'request_status',
        'verified_by', 'verified_at', 'verify_note',
        'order_date', 'pickup_date', 'notes',
        'delivery_type', 'origin', 'destination',
        'tracking_number', 'estimated_arrival',
        'cost', 'other_cost',
        // Field operasional muatan
        'checker', 'jenis_truck', 'depo', 'muat', 'tgl_muat', 'bongkar', 'tgl_bongkar',
        'komoditi', 'tujuan', 'sektor', 'kode_sektor', 'no_container', 'no_seal', 'grade',
        'no_pol', 'supir', 'hp_supir', 'empty_full', 'bongkar_empty_full',
        'kota', 'kecamatan', 'kelurahan', 'keterangan',
        // Penagihan & approval DO
        'invoice_status', 'do_approved',
    ];

    protected $casts = [
        'order_date'        => 'date',
        'pickup_date'       => 'date',
        'estimated_arrival' => 'date',
        'verified_at'       => 'datetime',
        'tgl_muat'          => 'date',
        'tgl_bongkar'       => 'date',
        'do_approved'       => 'boolean',
    ];

    /** Tahapan alur Request DO */
    public const FLOW = [
        'draft'      => 'Draft / Request',
        'verifikasi' => 'Verifikasi Sales Admin',
        'dispatch'   => 'Transport Planner',
        'approval'   => 'Menunggu Approval',
        'assigned'   => 'Disetujui (DO Terbit)',
        'rejected'   => 'Ditolak',
        'cancelled'  => 'Dibatalkan',
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
    public function verifier(): BelongsTo  { return $this->belongsTo(User::class, 'verified_by'); }
    public function items(): HasMany       { return $this->hasMany(RequestOrderItem::class); }
    public function jobDetails(): HasMany  { return $this->hasMany(OrderJobDetail::class); }
    public function invoiceItems(): HasMany { return $this->hasMany(InvoiceItem::class); }
    public function assignment(): HasMany  { return $this->hasMany(OrderAssignment::class)->latest(); }
    public function deliveryOrder(): HasMany { return $this->hasMany(DeliveryOrder::class); }

    public function statusLogs(): MorphMany
    {
        return $this->morphMany(OrderStatusLog::class, 'loggable')->latest();
    }

    /**
     * Revenue (Jual): utamakan rincian pekerjaan (riil_jual), fallback ke item layanan.
     */
    public function getTotalRevenueAttribute(): float
    {
        if ($this->relationLoaded('jobDetails') || $this->jobDetails()->exists()) {
            $sum = $this->jobDetails->sum(fn($j) => (float) $j->riil_jual);
            if ($sum > 0) return $sum;
        }
        return $this->items->sum(fn($i) => $i->qty * $i->sell_price);
    }

    /**
     * HPP (Cost): utamakan rincian pekerjaan (riil_biaya), fallback ke item layanan.
     */
    public function getTotalCostAttribute(): float
    {
        if ($this->relationLoaded('jobDetails') || $this->jobDetails()->exists()) {
            $sum = $this->jobDetails->sum(fn($j) => (float) $j->riil_biaya);
            if ($sum > 0) return $sum;
        }
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

    public function getFlowLabelAttribute(): string
    {
        return self::FLOW[$this->request_status] ?? ucfirst((string) $this->request_status);
    }

    public function getFlowColorAttribute(): string
    {
        return match($this->request_status) {
            'draft'      => 'secondary',
            'verifikasi' => 'warning',
            'dispatch'   => 'primary',
            'approval'   => 'purple',
            'assigned'   => 'success',
            'rejected', 'cancelled' => 'danger',
            default      => 'secondary',
        };
    }

    public static function generateDoNumber(): string
    {
        $prefix = 'RDO-' . date('Ym') . '-';
        $last   = static::withTrashed()->where('do_number', 'like', $prefix . '%')
            ->orderByDesc('do_number')->value('do_number');
        $seq    = $last ? (intval(substr($last, -4)) + 1) : 1;
        return $prefix . str_pad($seq, 4, '0', STR_PAD_LEFT);
    }

    /** Catat perpindahan status + simpan status baru. */
    public function transition(string $to, ?string $note = null, ?int $userId = null): void
    {
        $from = $this->request_status;
        $this->update(['request_status' => $to]);
        OrderStatusLog::record($this, $from, $to, $userId, $note);
    }
}
