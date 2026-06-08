<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class DeletionRequest extends Model
{
    protected $fillable = [
        'model_type', 'model_id', 'model_label', 'module',
        'status', 'reason', 'review_note',
        'requested_by', 'reviewed_by', 'reviewed_at',
    ];

    protected $casts = [
        'reviewed_at' => 'datetime',
    ];

    /**
     * Peta model => konfigurasi modul.
     * label_field : kolom yang dipakai sebagai label tampilan.
     * route       : nama route index modul (untuk badge & link).
     */
    public const MODULES = [
        \App\Models\Lead::class => [
            'module'      => 'leads',
            'label_field' => 'company_name',
            'route'       => 'leads.index',
            'title'       => 'Lead',
        ],
        \App\Models\Customer::class => [
            'module'      => 'customers',
            'label_field' => 'company_name',
            'route'       => 'customers.index',
            'title'       => 'Customer',
        ],
        \App\Models\Vendor::class => [
            'module'      => 'vendors',
            'label_field' => 'vendor_name',
            'route'       => 'vendors.index',
            'title'       => 'Vendor',
        ],
        \App\Models\DeliveryOrder::class => [
            'module'      => 'delivery-orders',
            'label_field' => 'do_number',
            'route'       => 'delivery-orders.index',
            'title'       => 'Delivery Order',
        ],
    ];

    public function target(): MorphTo
    {
        return $this->morphTo('model', 'model_type', 'model_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function isPending(): bool   { return $this->status === 'pending'; }
    public function isApproved(): bool  { return $this->status === 'approved'; }
    public function isRejected(): bool  { return $this->status === 'rejected'; }

    /** Konfigurasi modul untuk model_type ini */
    public function moduleConfig(): array
    {
        return self::MODULES[$this->model_type] ?? [
            'module' => $this->module ?? 'unknown',
            'title'  => class_basename($this->model_type),
            'route'  => 'dashboard',
        ];
    }

    public function getModuleTitleAttribute(): string
    {
        return $this->moduleConfig()['title'] ?? class_basename($this->model_type);
    }

    /**
     * Buat permintaan hapus (idempotent: kalau sudah ada pending, kembalikan yang lama).
     */
    public static function request(Model $model, int $userId, ?string $reason = null): self
    {
        $existing = static::where('model_type', $model->getMorphClass())
            ->where('model_id', $model->getKey())
            ->where('status', 'pending')
            ->first();

        if ($existing) {
            return $existing;
        }

        $config = self::MODULES[get_class($model)] ?? null;
        $labelField = $config['label_field'] ?? null;
        $label = $labelField ? ($model->{$labelField} ?? null) : null;

        return static::create([
            'model_type'   => $model->getMorphClass(),
            'model_id'     => $model->getKey(),
            'model_label'  => $label,
            'module'       => $config['module'] ?? null,
            'status'       => 'pending',
            'reason'       => $reason,
            'requested_by' => $userId,
        ]);
    }

    /** Apakah model ini sedang punya request hapus pending? */
    public static function pendingFor(Model $model): bool
    {
        return static::where('model_type', $model->getMorphClass())
            ->where('model_id', $model->getKey())
            ->where('status', 'pending')
            ->exists();
    }

    /** Set ID model yang sedang pending per modul — untuk badge di list (1 query). */
    public static function pendingIdsFor(string $modelClass): array
    {
        return static::where('model_type', (new $modelClass)->getMorphClass())
            ->where('status', 'pending')
            ->pluck('model_id')
            ->all();
    }
}
