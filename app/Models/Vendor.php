<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Vendor extends Model
{
    use SoftDeletes;

    protected $fillable = [
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

    public const SERVICE_TYPES = [
        'Material Handling',
        'Warehousing & Distribution',
        'Transportation Service',
        'Freight Forwarding',
        'Door to Door Service',
        'Project Logistics',
        'Cold Chain Logistics',
        'Lainnya',
    ];

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
}