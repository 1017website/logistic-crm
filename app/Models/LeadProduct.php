<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Schema;

class LeadProduct extends Model
{
    protected $fillable = ['lead_id', 'service_name', 'product_name', 'qty', 'unit', 'tonnage', 'shipping_zone'];
    protected $appends = ['display_name'];

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function getDisplayNameAttribute(): string
    {
        return (string) (($this->attributes['service_name'] ?? null) ?: ($this->attributes['product_name'] ?? '') ?: '');
    }

    public function getProductNameAttribute($value): string
    {
        return (string) (($this->attributes['service_name'] ?? null) ?: $value ?: '');
    }

    public function setProductNameAttribute($value): void
    {
        $this->attributes['product_name'] = $value;
        if (Schema::hasColumn($this->getTable(), 'service_name')) {
            $this->attributes['service_name'] = $value;
        }
    }

    public function setServiceNameAttribute($value): void
    {
        if (Schema::hasColumn($this->getTable(), 'service_name')) {
            $this->attributes['service_name'] = $value;
        }
        $this->attributes['product_name'] = $value;
    }
}
