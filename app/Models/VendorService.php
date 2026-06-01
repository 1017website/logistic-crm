<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VendorService extends Model
{
    protected $fillable = [
        'vendor_id', 'service_name', 'unit', 'tonnage', 'tariff', 'tariff_unit',
        'route_origin', 'route_destination', 'description',
    ];

    protected $casts = [
        'tariff' => 'decimal:2',
    ];

    public const TARIFF_UNITS = ['per kg', 'per km', 'per m³', 'per kontainer', 'flat'];

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    /** Format tampilan rute */
    public function getRouteAttribute(): ?string
    {
        if (!$this->route_origin && !$this->route_destination) return null;
        return trim(($this->route_origin ?? '-') . ' → ' . ($this->route_destination ?? '-'));
    }
}
