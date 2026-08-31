<?php

namespace App\Models;

use App\Models\RequestOrder;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    use SoftDeletes;
    protected $fillable = [
        'customer_code','invoice_code','top_days','company_name','pic_name','pic_position','phone','email','address',
        'industry','location','status','value_tag','user_id','customer_since','logo','notes','products'
    ];

    protected $casts = ['customer_since' => 'date'];

    /** Default TOP bila customer maupun setting global belum diisi. */
    public const FALLBACK_TOP_DAYS = 30;

    public function user(): BelongsTo         { return $this->belongsTo(User::class, 'user_id'); }
    public function salesUser(): BelongsTo    { return $this->belongsTo(User::class, 'user_id'); }
    public function leads(): HasMany          { return $this->hasMany(Lead::class); }
    public function activities(): HasMany     { return $this->hasMany(Activity::class); }
    // Revenue & status order tersimpan di request_orders (eks delivery_orders lama).
    public function deliveryOrders(): HasMany { return $this->hasMany(RequestOrder::class); }
    public function requestOrders(): HasMany  { return $this->hasMany(RequestOrder::class); }
    public function finalDeliveryOrders(): HasMany { return $this->hasMany(DeliveryOrder::class); }
    public function invoices(): HasMany       { return $this->hasMany(Invoice::class); }
    public function pics(): HasMany           { return $this->hasMany(CustomerPic::class); }

    // Kebutuhan layanan (field disamakan dengan vendor_services)
    public function productItems(): HasMany   { return $this->hasMany(CustomerProduct::class); }

    public function getTotalRevenueAttribute(): float
    {
        return $this->deliveryOrders()
            ->where('status', 'Done')->where('currency', 'IDR')
            ->with('items')->get()->sum(fn($so) => $so->total_revenue);
    }

    public function getLogoInitialsAttribute(): string
    {
        $name = trim((string) $this->company_name);
        if ($name === '') {
            return 'CU';
        }

        $parts = preg_split('/\s+/', $name);
        $initials = '';
        foreach (array_slice($parts, 0, 2) as $part) {
            $initials .= strtoupper(substr($part, 0, 1));
        }

        return $initials ?: 'CU';
    }

    public function getInvoiceNumberCodeAttribute(): string
    {
        return $this->invoice_code ?: ($this->customer_code ?: 'CUST');
    }

    /**
     * TOP yang berlaku untuk customer ini: pakai TOP customer bila diisi,
     * kalau tidak ikut default global yang diatur di menu Pengaturan.
     */
    public function getEffectiveTopDaysAttribute(): int
    {
        return (int) ($this->top_days ?: static::defaultTopDays());
    }

    /** Due date invoice untuk customer ini dari tanggal invoice tertentu. */
    public function dueDateFrom(\DateTimeInterface|string $invoiceDate): string
    {
        return \Illuminate\Support\Carbon::parse($invoiceDate)
            ->addDays($this->effective_top_days)
            ->toDateString();
    }

    /** Default TOP global (hari). Minimal 1 hari agar due date tidak sama dengan tanggal invoice. */
    public static function defaultTopDays(): int
    {
        $days = (int) Setting::get('invoice_default_top_days', self::FALLBACK_TOP_DAYS);

        return $days > 0 ? $days : self::FALLBACK_TOP_DAYS;
    }

    public static function normalizeInvoiceCode(?string $code): ?string
    {
        $code = strtoupper(trim((string) $code));
        return $code === '' ? null : $code;
    }

    public static function generateCustomerCode(): string
    {
        $prefix = 'CUST-' . date('Y') . '-';
        $last   = static::withTrashed()->where('customer_code', 'like', $prefix . '%')
            ->orderByDesc('customer_code')->value('customer_code');
        $seq    = $last ? (intval(substr($last, -4)) + 1) : 1;
        return $prefix . str_pad($seq, 4, '0', STR_PAD_LEFT);
    }
}
