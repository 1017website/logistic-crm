<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Vendor extends Model
{
    protected $fillable = [
        'vendor_name','pic_name','pic_position','phone','email','address',
        'vendor_type','service_type','coverage_area','status','is_preferred',
        'rating','payment_term','vendor_since','logo'
    ];
    protected $casts = ['vendor_since' => 'date', 'is_preferred' => 'boolean'];

    public function deliveryOrders(): HasMany { return $this->hasMany(DeliveryOrder::class); }
    public function rates(): HasMany { return $this->hasMany(VendorRate::class); }

    public function getLogoInitialsAttribute(): string
    {
        $parts = explode(' ', $this->vendor_name);
        $initials = '';
        foreach (array_slice($parts, 0, 2) as $part) {
            $initials .= strtoupper(substr($part, 0, 1));
        }
        return $initials;
    }

    public function getOnTimeDeliveryAttribute(): int
    {
        $total = $this->deliveryOrders()->count();
        if ($total === 0) return 0;
        $onTime = $this->deliveryOrders()->where('status', 'Done')->count();
        return (int) round(($onTime / $total) * 100);
    }
}
