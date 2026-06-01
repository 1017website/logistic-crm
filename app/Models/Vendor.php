<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Vendor extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'vendor_code',
        'vendor_name',
        'pic_name',
        'pic_position',
        'phone',
        'email',
        'address',
        'vendor_type',
        'service_type',
        'service_mode',
        'status',
        'relationship_status',
        'is_preferred',
        'rating',
        'payment_term',
        'vendor_since',
        'logo',
    ];

    protected $casts = [
        'vendor_since' => 'date',
        'is_preferred' => 'boolean',
    ];

    /** Daftar enum (single source of truth) */
    public const VENDOR_TYPES = ['External', 'Internal'];

    /** Fallback default jika master service_types belum tersedia */
    public const SERVICE_TYPES_DEFAULT = [
        'Liner',
        'Internasional Freight Forwarding',
        'Domestic Freight Forwarding door to door',
        'Trucking trailer',
        'Trucking non trailer',
        'Forklift',
        'Alat berat (heavy duty)',
        'Ware house',
        'Genset',
        'Dump truck',
        'PPJK',
    ];

    /**
     * Opsi Service Type — diambil dari master (tabel service_types).
     * Aman dipanggil walau tabel belum dimigrasi (fallback ke default).
     */
    public static function serviceTypeOptions(): array
    {
        try {
            if (\Illuminate\Support\Facades\Schema::hasTable('service_types')) {
                $names = ServiceType::activeNames();
                if (!empty($names)) {
                    return $names;
                }
            }
        } catch (\Throwable $e) {
            // abaikan, pakai fallback
        }
        return self::SERVICE_TYPES_DEFAULT;
    }

    /** @deprecated gunakan serviceTypeOptions() — dipertahankan utk kompatibilitas */
    public const SERVICE_TYPES = self::SERVICE_TYPES_DEFAULT;

    public const SERVICE_MODES = ['Tracking', 'Kontainer', 'Wingbox'];

    public function deliveryOrders(): HasMany
    {
        return $this->hasMany(DeliveryOrder::class);
    }
    public function services(): HasMany
    {
        return $this->hasMany(VendorService::class);
    }
    public function pics(): HasMany
    {
        return $this->hasMany(VendorPic::class);
    }

    public function isExisting(): bool
    {
        return $this->relationship_status === 'Existing';
    }
    public function isPotential(): bool
    {
        return $this->relationship_status === 'Potential';
    }
    public function isExternal(): bool
    {
        return $this->vendor_type === 'External';
    }
    public function isInternal(): bool
    {
        return $this->vendor_type === 'Internal';
    }

    /** Helper: service_mode disimpan comma-separated, expose sebagai array */
    public function getServiceModesArrayAttribute(): array
    {
        return $this->service_mode ? array_map('trim', explode(',', $this->service_mode)) : [];
    }

    public static function generateVendorCode(): string
    {
        $prefix = 'VND-' . date('Y') . '-';
        $last   = static::withTrashed()->where('vendor_code', 'like', $prefix . '%')
            ->orderByDesc('vendor_code')->value('vendor_code');
        $seq    = $last ? (intval(substr($last, -4)) + 1) : 1;
        return $prefix . str_pad($seq, 4, '0', STR_PAD_LEFT);
    }
}