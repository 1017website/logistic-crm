<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VendorPic extends Model
{
    protected $fillable = ['vendor_id', 'pic_name', 'pic_position', 'phone', 'email', 'is_primary'];

    protected $casts = ['is_primary' => 'boolean'];

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }
}
