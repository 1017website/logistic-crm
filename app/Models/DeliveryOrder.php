<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

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
        'status', 'invoice_status', 'assignment_type', 'fleet_info', 'driver_name', 'driver_phone',
        'origin', 'destination', 'do_date', 'pickup_date', 'delivery_date',
        'surat_jalan_file', 'pod_file', 'pod_at', 'pod_verified_by', 'pod_verified_at',
        'actual_cost', 'other_cost', 'closed_by', 'closed_at', 'notes',
    ];

    protected $casts = [
        'do_date' => 'date',
        'pickup_date' => 'date',
        'delivery_date' => 'date',
        'pod_at' => 'datetime',
        'pod_verified_at' => 'datetime',
        'closed_at' => 'datetime',
        'actual_cost' => 'decimal:0',
        'other_cost' => 'decimal:0',
    ];

    public const FLOW = [
        'returned_to_rdo' => 'Dikembalikan ke RDO',
        'surat_jalan' => 'Surat Jalan',
        'pickup' => 'Pickup',
        'in_delivery' => 'Delivery',
        'pod' => 'Menunggu Upload POD',
        'verifikasi_pod' => 'POD Diterima (Menunggu Verifikasi)',
        'closed' => 'DO Ditutup',
        'invoiced' => 'Invoice Terbit',
        'paid' => 'Lunas',
        'cancelled' => 'Dibatalkan',
    ];

    public const NEXT = [
        'surat_jalan' => 'pickup',
        'pickup' => 'in_delivery',
        'in_delivery' => 'pod',
        'pod' => 'verifikasi_pod',
        'verifikasi_pod' => 'closed',
        'closed' => 'invoiced',
        'invoiced' => 'paid',
    ];

    public function getCanReturnToRequestAttribute(): bool
    {
        return ! $this->trashed() && $this->status === 'surat_jalan'
            && $this->invoice_status === 'uninvoiced'
            && $this->requestOrder?->request_status === 'assigned'
            && $this->requestOrder?->invoice_status === 'uninvoiced'
            && ! $this->pod_file && ! $this->pod_at && ! $this->closed_at
            && ! InvoiceItem::where(function ($query) {
                $query->where('delivery_order_id', $this->id)
                    ->orWhere('request_order_id', $this->request_order_id);
            })->exists();
    }

    public function requestOrder(): BelongsTo
    {
        return $this->belongsTo(RequestOrder::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function salesUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function podVerifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pod_verified_by');
    }

    public function closer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function invoiceItems(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

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
        return match ($this->status) {
            'surat_jalan' => 'secondary',
            'pickup' => 'primary',
            'in_delivery' => 'warning',
            'pod', 'verifikasi_pod' => 'purple',
            'closed' => 'indigo',
            'invoiced' => 'warning',
            'paid' => 'success',
            'cancelled' => 'danger',
            default => 'secondary',
        };
    }

    public function getInvoiceStatusLabelAttribute(): string
    {
        return match ($this->invoice_status) {
            'partial' => 'Ditagih Sebagian',
            'invoiced' => 'Sudah Diinvoice',
            'paid' => 'Lunas',
            default => 'Belum Diinvoice',
        };
    }

    /**
     * Nilai yang dapat ditagih per tipe layanan pada DO final.
     *
     * Detail pekerjaan berkode TR masuk Trucking; kode lainnya masuk
     * Non-Trucking. Data lama tanpa rincian pekerjaan tetap dianggap Trucking.
     *
     * @return array<string, array{
     *     type:string,
     *     hpp:float,
     *     jual:float,
     *     description:string,
     *     lines:array<int, array{item_name:string,description:string,quantity:float,unit_price:float,hpp:float,jual:float}>
     * }>
     */
    public function invoiceBreakdown(): array
    {
        if (! $this->relationLoaded('requestOrder')) {
            $this->load('requestOrder.jobDetails', 'requestOrder.items');
        } elseif ($this->requestOrder) {
            $missing = [];
            if (! $this->requestOrder->relationLoaded('jobDetails')) {
                $missing[] = 'requestOrder.jobDetails';
            }
            if (! $this->requestOrder->relationLoaded('items')) {
                $missing[] = 'requestOrder.items';
            }
            if ($missing !== []) {
                $this->load($missing);
            }
        }
        $requestOrder = $this->requestOrder;

        if (! $requestOrder) {
            return [];
        }

        $result = [];
        foreach ($requestOrder->jobDetails as $detail) {
            $code = strtoupper(trim((string) $detail->job_code));
            $type = match ($code) {
                'TR' => 'TR',
                'NTR' => 'NTR',
                default => str_contains(strtolower((string) $detail->job_name), 'truck') ? 'TR' : 'NTR',
            };
            $result[$type] ??= [
                'type' => $type,
                'hpp' => 0.0,
                'jual' => 0.0,
                'names' => [],
                'lines' => [],
            ];
            $hpp = (float) $detail->riil_biaya;
            $jual = (float) $detail->riil_jual;
            $name = mb_substr(
                trim((string) $detail->job_name) ?: ($type === 'TR' ? 'Trucking' : 'Non-Trucking'),
                0,
                255
            );
            $result[$type]['hpp'] += $hpp;
            $result[$type]['jual'] += $jual;
            if ($detail->job_name) {
                $result[$type]['names'][] = $detail->job_name;
            }
            if ($hpp > 0 || $jual > 0) {
                $result[$type]['lines'][] = [
                    'item_name' => $name,
                    'description' => mb_substr(trim((string) $detail->catatan) ?: $name, 0, 255),
                    'quantity' => 1.0,
                    'unit_price' => $jual,
                    'hpp' => $hpp,
                    'jual' => $jual,
                ];
            }
        }

        // Item Layanan dapat menjadi sumber nilai tipe yang belum dicatat pada
        // Rincian Biaya per Pekerjaan. Satu tipe tidak dijumlahkan dari kedua
        // tabel agar input Finance yang merepresentasikan layanan yang sama
        // tidak terhitung dua kali.
        $jobTypes = collect($result)
            ->filter(fn (array $row) => $row['hpp'] > 0 || $row['jual'] > 0)
            ->keys();
        foreach ($requestOrder->items as $item) {
            $type = $item->service_type === 'NTR' ? 'NTR' : 'TR';
            if ($jobTypes->contains($type)) {
                continue;
            }
            $result[$type] ??= [
                'type' => $type,
                'hpp' => 0.0,
                'jual' => 0.0,
                'names' => [],
                'lines' => [],
            ];
            $quantity = max(0.001, (float) $item->qty);
            $hpp = $quantity * (float) $item->buy_price;
            $jual = $quantity * (float) $item->sell_price;
            $name = mb_substr(
                trim((string) $item->service_name) ?: ($type === 'TR' ? 'Trucking' : 'Non-Trucking'),
                0,
                255
            );
            $result[$type]['hpp'] += $hpp;
            $result[$type]['jual'] += $jual;
            if ($item->service_name) {
                $result[$type]['names'][] = $item->service_name;
            }
            if ($hpp > 0 || $jual > 0) {
                $result[$type]['lines'][] = [
                    'item_name' => $name,
                    'description' => mb_substr(trim((string) $item->description) ?: $name, 0, 255),
                    'quantity' => $quantity,
                    'unit_price' => (float) $item->sell_price,
                    'hpp' => $hpp,
                    'jual' => $jual,
                ];
            }
        }

        $hasValue = collect($result)->contains(
            fn (array $row) => $row['hpp'] > 0 || $row['jual'] > 0
        );

        if (! $hasValue) {
            $result = [
                'TR' => [
                    'type' => 'TR',
                    'hpp' => (float) $this->total_cost,
                    'jual' => (float) $this->total_revenue,
                    'names' => ['Trucking'],
                    'lines' => [[
                        'item_name' => 'Trucking',
                        'description' => 'Trucking',
                        'quantity' => 1.0,
                        'unit_price' => (float) $this->total_revenue,
                        'hpp' => (float) $this->total_cost,
                        'jual' => (float) $this->total_revenue,
                    ]],
                ],
            ];
        }

        return collect($result)
            ->filter(fn (array $row) => $row['hpp'] > 0 || $row['jual'] > 0)
            ->map(function (array $row) {
                $names = array_values(array_unique(array_filter($row['names'])));

                return [
                    'type' => $row['type'],
                    'hpp' => $row['hpp'],
                    'jual' => $row['jual'],
                    'description' => $names !== []
                        ? implode(', ', $names)
                        : ($row['type'] === 'TR' ? 'Trucking' : 'Non-Trucking'),
                    'lines' => $row['lines'],
                ];
            })
            ->all();
    }

    public static function generateDoNumber(): string
    {
        $prefix = 'DO-'.date('Ym').'-';
        $last = static::withTrashed()->where('do_number', 'like', $prefix.'%')
            ->orderByDesc('do_number')->value('do_number');
        $seq = $last ? (intval(substr($last, -4)) + 1) : 1;

        return $prefix.str_pad($seq, 4, '0', STR_PAD_LEFT);
    }

    public function transition(string $to, ?string $note = null, ?int $userId = null): void
    {
        $from = $this->status;
        $this->update(['status' => $to]);
        OrderStatusLog::record($this, $from, $to, $userId, $note);
    }
}
