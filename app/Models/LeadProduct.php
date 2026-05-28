<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeadProduct extends Model
{
    protected $fillable = ['lead_id', 'product_name', 'qty', 'unit'];

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }
}
