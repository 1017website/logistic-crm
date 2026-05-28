<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeadPic extends Model
{
    protected $fillable = ['lead_id', 'pic_name', 'pic_position', 'phone', 'email', 'is_primary'];

    protected $casts = ['is_primary' => 'boolean'];

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }
}
