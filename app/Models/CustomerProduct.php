<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerProduct extends Model
{
    protected $fillable = ['customer_id', 'service_name', 'product_name', 'qty', 'unit', 'description'];
    protected $appends = ['display_name'];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
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
