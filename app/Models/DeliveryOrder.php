<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeliveryOrder extends Model
{
    protected $fillable = [
        'do_number','customer_id','vendor_id','lead_id',
        'service_type','route','amount','currency','status','order_date'
    ];

    protected $casts = [
        'order_date' => 'date',
        'amount'     => 'decimal:0',
    ];

    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }
    public function vendor(): BelongsTo   { return $this->belongsTo(Vendor::class); }
    public function lead(): BelongsTo     { return $this->belongsTo(Lead::class); }
}
