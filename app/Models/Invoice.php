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
        'tgl_buat', 'tgl_tempo', 'tgl_pencairan',
        'total_hpp', 'total_jual', 'ppn_persen', 'ppn_nominal', 'grand_total',
        'jenis', 'operator_id', 'notes',
    ];

    protected $casts = [
        'tgl_buat'      => 'date',
        'tgl_tempo'     => 'date',
        'tgl_pencairan' => 'date',
        'total_hpp'     => 'decimal:0',
        'total_jual'    => 'decimal:0',
        'ppn_persen'    => 'decimal:2',
        'ppn_nominal'   => 'decimal:0',
        'grand_total'   => 'decimal:0',
    ];

    public const STATUS = [
        'draft'   => 'Draft',
        'invoice' => 'Invoice',
        'paid'    => 'Lunas',
    ];

    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }
    public function operator(): BelongsTo { return $this->belongsTo(User::class, 'operator_id'); }
    public function items(): HasMany      { return $this->hasMany(InvoiceItem::class); }

    public function getLabaAttribute(): float
    {
        return (float) $this->total_jual - (float) $this->total_hpp;
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUS[$this->status] ?? ucfirst((string) $this->status);
    }

    public function getStatusColorAttribute(): string
    {
        return match($this->status) {
            'draft'   => 'secondary',
            'invoice' => 'warning',
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
