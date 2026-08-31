<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * REQUEST DO — order yang dibuat Sales, diverifikasi Sales Admin,
 * lalu ditugaskan oleh Sales Admin/Transport Planner & disetujui
 * (Approval Penugasan).
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
        'operational_status', 'operational_note', 'rescheduled_for',
        'operational_status_changed_by', 'operational_status_changed_at',
        'dp_status', 'dp_request_active', 'dp_amount', 'dp_note', 'dp_reviewed_by', 'dp_reviewed_at',
        'cancelled_by', 'cancelled_at', 'cancel_reason',
        'verified_by', 'verified_at', 'verify_note',
        'order_date', 'pickup_date', 'notes',
        'delivery_type', 'origin', 'destination',
        'tracking_number', 'estimated_arrival',
        'cost', 'other_cost',
        // Field operasional muatan
        'checker', 'jenis_truck', 'depo', 'muat', 'tgl_muat', 'bongkar', 'tgl_bongkar',
        'komoditi', 'tujuan', 'sektor', 'kode_sektor', 'no_container', 'no_seal', 'grade',
        'no_pol', 'supir', 'hp_supir', 'empty_full', 'bongkar_empty_full',
        'kota', 'alamat', 'kecamatan', 'kelurahan', 'keterangan',
        // Penagihan & approval DO
        'invoice_status', 'do_approved', 'price_correction_open',
    ];

    protected $casts = [
        'order_date'        => 'date',
        'pickup_date'       => 'date',
        'rescheduled_for'   => 'date',
        'estimated_arrival' => 'date',
        'verified_at'       => 'datetime',
        'operational_status_changed_at' => 'datetime',
        'dp_amount'          => 'decimal:0',
        'dp_request_active'  => 'boolean',
        'dp_reviewed_at'     => 'datetime',
        'cancelled_at'       => 'datetime',
        'tgl_muat'          => 'date',
        'tgl_bongkar'       => 'date',
        'do_approved'       => 'boolean',
        'price_correction_open' => 'boolean',
    ];

    /** Tahapan alur Request DO */
    public const FLOW = [
        'draft'      => 'Draft / Request',
        'verifikasi' => 'Verifikasi Sales Admin',
        'finance'    => 'Review Finance & DP',
        'dispatch'   => 'Transport Planner',
        'approval'   => 'Menunggu Approval',
        'assigned'   => 'Disetujui (DO Terbit)',
        'rejected'   => 'Ditolak',
        'cancelled'  => 'Dibatalkan',
    ];

    /** Status pelaksanaan DO, terpisah dari tahap approval/fulfillment. */
    public const OPERATIONAL_STATUSES = [
        'running'     => 'Jalan / Aktif',
        'pending'     => 'Pending',
        'rescheduled' => 'Reschedule',
        'cancelled'   => 'Cancel',
    ];

    public const DP_STATUSES = [
        'pending'   => 'Belum Direview',
        'taken'     => 'DP Terambil',
        'not_taken' => 'DP Tidak Terambil',
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
    public function operationalStatusChanger(): BelongsTo
    {
        return $this->belongsTo(User::class, 'operational_status_changed_by');
    }
    public function dpReviewer(): BelongsTo { return $this->belongsTo(User::class, 'dp_reviewed_by'); }
    public function canceller(): BelongsTo { return $this->belongsTo(User::class, 'cancelled_by'); }
    public function items(): HasMany       { return $this->hasMany(RequestOrderItem::class); }
    public function jobDetails(): HasMany
    {
        $relation = $this->hasMany(OrderJobDetail::class);

        // Perlindungan tambahan untuk database hasil impor yang pernah memakai ulang ID.
        if ($this->created_at) {
            $relation->where('order_job_details.created_at', '>=', $this->created_at);
        }

        return $relation;
    }
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

    /**
     * HPP realisasi lapangan: biaya aktual + biaya lain yang diinput saat DO
     * final ditutup. Null bila belum ada realisasi yang tercatat.
     *
     * Dipisahkan dari total_cost (HPP rencana yang disetujui Sales Manager dan
     * dipakai invoice) supaya selisihnya terlihat, bukan saling menimpa.
     */
    public function getActualTotalCostAttribute(): ?float
    {
        $orders = $this->relationLoaded('deliveryOrder')
            ? $this->deliveryOrder
            : $this->deliveryOrder()->get();

        if ($orders->isEmpty()) {
            return null;
        }

        $sum = (float) $orders->sum(
            fn(DeliveryOrder $order) => (float) $order->actual_cost + (float) $order->other_cost
        );

        return $sum > 0 ? $sum : null;
    }

    /** Selisih HPP realisasi terhadap rencana. Positif = realisasi lebih mahal. */
    public function getCostVarianceAttribute(): ?float
    {
        $actual = $this->actual_total_cost;

        return $actual === null ? null : $actual - $this->total_cost;
    }

    /** Laba memakai HPP realisasi. Null bila realisasi belum tercatat. */
    public function getActualGrossProfitAttribute(): ?float
    {
        $actual = $this->actual_total_cost;

        return $actual === null ? null : $this->total_revenue - $actual;
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
            'finance'    => 'info',
            'dispatch'   => 'primary',
            'approval'   => 'purple',
            'assigned'   => 'success',
            'rejected', 'cancelled' => 'danger',
            default      => 'secondary',
        };
    }

    public function getDpStatusLabelAttribute(): string
    {
        return self::DP_STATUSES[$this->dp_status ?? 'pending'] ?? ucfirst((string) $this->dp_status);
    }

    public function getDpStatusColorAttribute(): string
    {
        return match($this->dp_status ?? 'pending') {
            'taken'     => 'success',
            'not_taken' => 'secondary',
            default     => 'warning',
        };
    }

    public function getOperationalStatusLabelAttribute(): string
    {
        return self::OPERATIONAL_STATUSES[$this->operational_status ?? 'running'] ?? ucfirst((string) $this->operational_status);
    }

    public function getOperationalStatusColorAttribute(): string
    {
        return match($this->operational_status ?? 'running') {
            'running'     => 'success',
            'pending'     => 'warning',
            'rescheduled' => 'info',
            'cancelled'   => 'danger',
            default       => 'secondary',
        };
    }

    public function getIsOperationallyInactiveAttribute(): bool
    {
        return ($this->operational_status ?? 'running') !== 'running';
    }

    /**
     * Rincian harga (item layanan & rincian pekerjaan) boleh diubah bila:
     *   - Request DO masih di tahap review Finance atau menunggu approval, ATAU
     *   - DO final sudah terbit tetapi Sales Manager membuka kunci koreksi harga
     *     lewat Unapprove.
     *
     * Apa pun tahapnya, harga tidak pernah dapat diubah setelah komponennya
     * masuk invoice.
     */
    public function getPricingEditableAttribute(): bool
    {
        if ($this->invoice_status !== 'uninvoiced') {
            return false;
        }

        return in_array($this->request_status, ['finance', 'approval'], true)
            || ($this->request_status === 'assigned' && (bool) $this->price_correction_open);
    }

    /** Label untuk timeline yang memuat tahap flow dan perubahan status operasional. */
    public static function statusLogLabel(?string $status): string
    {
        if (!$status) return '-';

        if (str_starts_with($status, 'operational_')) {
            $key = substr($status, strlen('operational_'));
            return 'Status DO: ' . (self::OPERATIONAL_STATUSES[$key] ?? ucfirst($key));
        }

        if ($status === 'dp_activated') return 'Request DP Diaktifkan';
        if ($status === 'dp_deactivated') return 'Request DP Dinonaktifkan';
        if ($status === 'price_correction_open') return 'Koreksi Harga Dibuka';
        if ($status === 'price_correction') return 'Harga DO Direvisi';

        return self::FLOW[$status] ?? ($status === 'do_approved' ? 'DO Disetujui' : ucfirst($status));
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

    /**
     * Catat dan beri tahu Sales Manager ketika data yang sedang menunggu
     * approval diubah. Tahap tetap approval agar perubahan langsung menjadi
     * pengajuan terbaru tanpa menerbitkan DO atau mengulang tahap sebelumnya.
     */
    public function resubmitManagerApproval(string $changeNote, ?int $userId = null): bool
    {
        if ($this->request_status !== 'approval') {
            return false;
        }

        $actorId = $userId ?? auth()->id();
        OrderStatusLog::record($this, 'approval', 'approval', $actorId, $changeNote);

        User::where('role', 'Sales Manager')->where('status', 'Active')->each(function (User $manager) {
            Notification::send(
                $manager->id,
                'request_do_manager_approval',
                'Request DO diajukan ulang',
                $this->do_number . ' telah diperbarui dan diajukan ulang untuk approval Sales Manager.',
                route('request-orders.show', $this)
            );
        });

        return true;
    }

    /**
     * Beri tahu Sales Manager ketika harga direvisi lewat jalur koreksi setelah
     * DO final terbit. Tahap flow tidak diubah — DO tetap berjalan, hanya harga
     * yang menunggu persetujuan ulang.
     */
    public function notifyPriceCorrection(string $changeNote, ?int $userId = null): bool
    {
        if ($this->request_status !== 'assigned' || !$this->price_correction_open) {
            return false;
        }

        $actorId = $userId ?? auth()->id();
        OrderStatusLog::record($this, null, 'price_correction', $actorId, $changeNote);

        User::where('role', 'Sales Manager')->where('status', 'Active')->each(function (User $manager) {
            Notification::send(
                $manager->id,
                'request_do_price_correction',
                'Harga DO direvisi',
                $this->do_number . ' harganya direvisi dan menunggu approve ulang.',
                route('request-orders.show', $this)
            );
        });

        return true;
    }
}
