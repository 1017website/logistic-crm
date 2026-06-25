<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Rincian biaya per pekerjaan dalam sebuah Request DO.
 * Sumber perhitungan HPP (riil_biaya) & Jual (riil_jual) sebuah DO.
 */
class OrderJobDetail extends Model
{
    protected $table = 'order_job_details';

    protected $fillable = [
        'request_order_id', 'pekerjaan_id', 'job_name', 'job_code', 'tgl_transaksi',
        'anggaran_biaya', 'anggaran_jual', 'riil_biaya', 'riil_jual', 'dibayar',
        'vendor_id', 'status_pembayaran', 'tgl_realisasi', 'catatan',
    ];

    protected $casts = [
        'tgl_transaksi'  => 'date',
        'tgl_realisasi'  => 'date',
        'anggaran_biaya' => 'decimal:0',
        'anggaran_jual'  => 'decimal:0',
        'riil_biaya'     => 'decimal:0',
        'riil_jual'      => 'decimal:0',
        'dibayar'        => 'decimal:0',
    ];

    public function requestOrder(): BelongsTo { return $this->belongsTo(RequestOrder::class); }
    public function pekerjaan(): BelongsTo    { return $this->belongsTo(Pekerjaan::class); }
    public function vendor(): BelongsTo       { return $this->belongsTo(Vendor::class); }

    public function getLabaAttribute(): float
    {
        return (float) $this->riil_jual - (float) $this->riil_biaya;
    }
}
