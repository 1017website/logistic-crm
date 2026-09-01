<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Invoice penagihan — bisa menggabungkan banyak DO untuk satu customer.
 *
 * Penomoran:
 *   invoice_number = {seq per customer (jalan terus)}/{invoice_code customer}/FTINV/{romawi bulan}/{tahun}
 *   contoh: 0002/MIF/FTINV/VI/2026
 */
class Invoice extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'invoice_id', 'invoice_number', 'customer_seq', 'customer_id', 'status',
        'tgl_buat', 'periode_invoice', 'submitted_at', 'tgl_tempo', 'tgl_tempo_manual', 'tgl_pencairan',
        'total_hpp', 'total_jual', 'ppn_persen', 'ppn_nominal', 'grand_total',
        'jenis', 'billing_mode', 'operator_id', 'notes',
        'edit_request_status', 'edit_request_reason', 'edit_requested_by', 'edit_requested_at',
        'edit_reviewed_by', 'edit_reviewed_at', 'edit_review_note',
    ];

    protected $casts = [
        'tgl_buat'      => 'date',
        'periode_invoice' => 'date',
        'submitted_at'  => 'datetime',
        'tgl_tempo'     => 'date',
        'tgl_tempo_manual' => 'boolean',
        'tgl_pencairan' => 'date',
        'total_hpp'     => 'decimal:0',
        'total_jual'    => 'decimal:0',
        'ppn_persen'    => 'decimal:2',
        'ppn_nominal'   => 'decimal:0',
        'grand_total'   => 'decimal:0',
        'edit_requested_at' => 'datetime',
        'edit_reviewed_at' => 'datetime',
    ];

    public const STATUS = [
        'draft'   => 'Draft',
        'invoice' => 'Invoice',
        'termin'  => 'Termin',
        'paid'    => 'Lunas',
    ];

    public const TYPES = [
        'TR'  => 'Trucking',
        'NTR' => 'Non-Trucking',
        'MIX' => 'Trucking & Non-Trucking',
    ];

    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }
    public function operator(): BelongsTo { return $this->belongsTo(User::class, 'operator_id'); }
    public function editRequester(): BelongsTo { return $this->belongsTo(User::class, 'edit_requested_by'); }
    public function editReviewer(): BelongsTo { return $this->belongsTo(User::class, 'edit_reviewed_by'); }
    public function items(): HasMany      { return $this->hasMany(InvoiceItem::class); }
    public function payments(): HasMany   { return $this->hasMany(InvoicePayment::class); }

    public function getTotalPaidAttribute(): float
    {
        $paid = $this->relationLoaded('payments')
            ? (float) $this->payments->sum('amount')
            : (float) $this->payments()->sum('amount');

        // Menjaga kompatibilitas jika ada invoice lunas lama yang belum sempat dibackfill.
        if ($paid <= 0 && $this->status === 'paid') {
            return (float) ($this->grand_total ?: $this->total_jual);
        }

        return $paid;
    }

    public function getOutstandingAttribute(): float
    {
        return max(0, (float) ($this->grand_total ?: $this->total_jual) - $this->total_paid);
    }

    public function getLabaAttribute(): float
    {
        return (float) $this->total_jual - (float) $this->total_hpp;
    }

    /** Jumlah DO unik di dalam invoice, bukan jumlah komponen TR/NTR. */
    public function getDoCountAttribute(): int
    {
        return $this->items
            ->map(fn (InvoiceItem $item) => $item->delivery_order_id
                ? 'do:' . $item->delivery_order_id
                : 'request:' . $item->request_order_id)
            ->filter(fn (string $key) => !str_ends_with($key, ':'))
            ->unique()
            ->count();
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUS[$this->status] ?? ucfirst((string) $this->status);
    }

    public function getJenisLabelAttribute(): string
    {
        return self::TYPES[$this->jenis] ?? '-';
    }

    public function getStatusColorAttribute(): string
    {
        return match($this->status) {
            'draft'   => 'secondary',
            'invoice' => 'warning',
            'termin'  => 'info',
            'paid'    => 'success',
            default   => 'secondary',
        };
    }

    public function getUmurHariAttribute(): ?int
    {
        if (!$this->tgl_tempo) return null;
        return now()->startOfDay()->diffInDays($this->tgl_tempo, false);
    }

    /** ID internal: IV{YYMM}{urut global 4 digit} */
    public static function generateInvoiceId(): string
    {
        $prefix = 'IV' . date('ym');
        $last   = static::withTrashed()->where('invoice_id', 'like', $prefix . '%')
            ->orderByDesc('invoice_id')->value('invoice_id');
        $seq    = $last ? (intval(substr($last, -4)) + 1) : 1;
        return $prefix . str_pad($seq, 4, '0', STR_PAD_LEFT);
    }

    /** Nomor urut per customer — JALAN TERUS (tidak reset). */
    public static function nextCustomerSeq(int $customerId): int
    {
        $max = static::withTrashed()->where('customer_id', $customerId)->max('customer_seq');
        return ((int) $max) + 1;
    }

    /** Bangun nomor invoice format {seq}/{code}/FTINV/{romawi}/{tahun}. */
    public static function buildInvoiceNumber(int $seq, ?string $customerInvoiceCode, ?\DateTimeInterface $date = null): string
    {
        $date  = $date ?? now();
        $month = (int) $date->format('n');
        $roman = self::toRoman($month);
        $year  = $date->format('Y');
        $code  = $customerInvoiceCode ?: 'CUST';
        return str_pad((string) $seq, 4, '0', STR_PAD_LEFT) . '/' . $code . '/FTINV/' . $roman . '/' . $year;
    }

    public static function toRoman(int $n): string
    {
        $map = [1=>'I',2=>'II',3=>'III',4=>'IV',5=>'V',6=>'VI',7=>'VII',8=>'VIII',9=>'IX',10=>'X',11=>'XI',12=>'XII'];
        return $map[$n] ?? (string) $n;
    }

    /** Terbilang rupiah (untuk cetak invoice). */
    public static function terbilang($angka): string
    {
        $angka = (int) abs($angka);
        $huruf = ['', 'satu', 'dua', 'tiga', 'empat', 'lima', 'enam', 'tujuh', 'delapan', 'sembilan', 'sepuluh', 'sebelas'];
        $hasil = '';
        if ($angka < 12) {
            $hasil = ' ' . $huruf[$angka];
        } elseif ($angka < 20) {
            $hasil = self::terbilang($angka - 10) . ' belas';
        } elseif ($angka < 100) {
            $hasil = self::terbilang(intval($angka / 10)) . ' puluh' . self::terbilang($angka % 10);
        } elseif ($angka < 200) {
            $hasil = ' seratus' . self::terbilang($angka - 100);
        } elseif ($angka < 1000) {
            $hasil = self::terbilang(intval($angka / 100)) . ' ratus' . self::terbilang($angka % 100);
        } elseif ($angka < 2000) {
            $hasil = ' seribu' . self::terbilang($angka - 1000);
        } elseif ($angka < 1000000) {
            $hasil = self::terbilang(intval($angka / 1000)) . ' ribu' . self::terbilang($angka % 1000);
        } elseif ($angka < 1000000000) {
            $hasil = self::terbilang(intval($angka / 1000000)) . ' juta' . self::terbilang($angka % 1000000);
        } elseif ($angka < 1000000000000) {
            $hasil = self::terbilang(intval($angka / 1000000000)) . ' miliar' . self::terbilang($angka % 1000000000);
        }
        return $hasil;
    }
}
