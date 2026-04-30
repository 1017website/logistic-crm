<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Quotation extends Model
{
    protected $fillable = [
        'quotation_number','lead_id','customer_id','service_type',
        'route','total_price','currency','sent_at','valid_until','status'
    ];

    protected $casts = [
        'sent_at'     => 'date',
        'valid_until' => 'date',
        'total_price' => 'decimal:0',
    ];

    public function lead(): BelongsTo     { return $this->belongsTo(Lead::class); }
    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }
}
