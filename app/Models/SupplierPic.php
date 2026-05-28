<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierPic extends Model
{
    protected $fillable = ['supplier_id', 'pic_name', 'pic_position', 'phone', 'email', 'is_primary'];

    protected $casts = ['is_primary' => 'boolean'];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }
}
