<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Master jenis pekerjaan logistik (mis. Trucking=TR, Empty=NTR).
 */
class Pekerjaan extends Model
{
    protected $table = 'pekerjaan';

    protected $fillable = ['name', 'code', 'type', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public const TYPES = ['TR' => 'Trucking', 'NTR' => 'Non-Trucking'];

    public function jobDetails(): HasMany { return $this->hasMany(OrderJobDetail::class); }

    public function getLabelAttribute(): string
    {
        return $this->code ? "{$this->name} - {$this->code}" : $this->name;
    }
}
