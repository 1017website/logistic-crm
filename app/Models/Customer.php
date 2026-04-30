<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    protected $fillable = [
        'company_name','pic_name','pic_position','phone','email','address',
        'industry','location','status','value_tag','sales_user_id','customer_since','logo'
    ];

    protected $casts = ['customer_since' => 'date'];

    public function salesUser(): BelongsTo { return $this->belongsTo(SalesUser::class); }
    public function leads(): HasMany { return $this->hasMany(Lead::class); }
    public function activities(): HasMany { return $this->hasMany(Activity::class); }
    public function deliveryOrders(): HasMany { return $this->hasMany(DeliveryOrder::class); }
    public function quotations(): HasMany { return $this->hasMany(Quotation::class); }

    public function getTotalRevenueAttribute(): float
    {
        return $this->deliveryOrders()->where('status', 'Done')->where('currency', 'IDR')->sum('amount');
    }

    public function getLogoInitialsAttribute(): string
    {
        $parts = explode(' ', $this->company_name);
        $initials = '';
        foreach (array_slice($parts, 0, 2) as $part) {
            $initials .= strtoupper(substr($part, 0, 1));
        }
        return $initials;
    }
}
