<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Schema;

class CustomerProduct extends Model
{
    // customer_products di database lama belum punya qty, dan beberapa database belum punya service_name.
    // Maka kebutuhan layanan disimpan minimal di product_name + unit agar aman di semua database existing.
    protected $fillable = ['customer_id', 'service_name', 'product_name', 'unit', 'description'];
    protected $appends = ['display_name'];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
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
