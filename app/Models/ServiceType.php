<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ServiceType extends Model
{
    protected $fillable = ['name', 'is_active', 'sort_order'];

    protected $casts = [
        'is_active'  => 'boolean',
        'sort_order' => 'integer',
    ];

    /** Daftar nama service type aktif (untuk dropdown vendor) */
    public static function activeNames(): array
    {
        return static::where('is_active', true)
            ->orderBy('sort_order')->orderBy('name')
            ->pluck('name')->all();
    }
}
