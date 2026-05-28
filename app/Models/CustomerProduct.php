<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerProduct extends Model
{
    protected $fillable = ['customer_id', 'product_name', 'qty', 'unit'];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
