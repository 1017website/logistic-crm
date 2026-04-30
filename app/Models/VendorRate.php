<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VendorRate extends Model
{
    protected $fillable = [
        'vendor_id','route','container_type','price','currency','last_updated'
    ];

    protected $casts = [
        'last_updated' => 'date',
        'price'        => 'decimal:0',
    ];

    public function vendor(): BelongsTo { return $this->belongsTo(Vendor::class); }
}
