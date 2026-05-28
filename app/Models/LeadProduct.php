<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeadProduct extends Model
{
    protected $fillable = ['lead_id', 'service_name', 'product_name', 'qty', 'unit'];
    protected $appends = ['display_name'];

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function getDisplayNameAttribute(): string
    {
        return (string) ($this->service_name ?: $this->product_name ?: '');
    }

    public function getProductNameAttribute($value): string
    {
        return (string) ($this->service_name ?: $value ?: '');
    }

    public function setProductNameAttribute($value): void
    {
        $this->attributes['service_name'] = $value;
        $this->attributes['product_name'] = $value;
    }
}
