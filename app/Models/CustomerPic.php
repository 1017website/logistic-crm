<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerPic extends Model
{
    protected $fillable = ['customer_id', 'pic_name', 'pic_position', 'phone', 'email', 'is_primary'];

    protected $casts = ['is_primary' => 'boolean'];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    protected static function booted(): void
    {
        static::saved(fn ($pic) => \App\Services\LeadCustomerSync::picSavedFromCustomer($pic));
        static::deleted(fn ($pic) => \App\Services\LeadCustomerSync::picDeletedFromCustomer($pic));
    }
}
