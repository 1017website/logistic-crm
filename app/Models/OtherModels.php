<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Activity extends Model
{
    protected $fillable = [
        'lead_id','customer_id','sales_user_id','type','subject',
        'description','activity_at','status','next_follow_up'
    ];
    protected $casts = ['activity_at' => 'datetime', 'next_follow_up' => 'date'];

    public function lead(): BelongsTo { return $this->belongsTo(Lead::class); }
    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }
    public function salesUser(): BelongsTo { return $this->belongsTo(SalesUser::class); }

    public function getTypeIconAttribute(): string
    {
        return match($this->type) {
            'Call' => 'phone',
            'Visit' => 'building',
            'Email' => 'envelope',
            'Note' => 'sticky-note',
            default => 'clock',
        };
    }

    public function getTypeColorAttribute(): string
    {
        return match($this->type) {
            'Call' => 'success',
            'Visit' => 'primary',
            'Email' => 'warning',
            'Note' => 'info',
            default => 'secondary',
        };
    }

    public function getStatusColorAttribute(): string
    {
        return match($this->status) {
            'Done' => 'success',
            'Pending' => 'warning',
            'Planned' => 'primary',
            'Overdue' => 'danger',
            default => 'secondary',
        };
    }
}

// ============================================================

namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeliveryOrder extends Model
{
    protected $fillable = [
        'do_number','customer_id','vendor_id','lead_id',
        'service_type','route','amount','currency','status','order_date'
    ];
    protected $casts = ['order_date' => 'date', 'amount' => 'decimal:0'];

    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }
    public function vendor(): BelongsTo { return $this->belongsTo(Vendor::class); }
    public function lead(): BelongsTo { return $this->belongsTo(Lead::class); }
}

// ============================================================

namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Quotation extends Model
{
    protected $fillable = [
        'quotation_number','lead_id','customer_id','service_type',
        'route','total_price','currency','sent_at','valid_until','status'
    ];
    protected $casts = ['sent_at' => 'date', 'valid_until' => 'date', 'total_price' => 'decimal:0'];

    public function lead(): BelongsTo { return $this->belongsTo(Lead::class); }
    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }
}

// ============================================================

namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VendorRate extends Model
{
    protected $fillable = ['vendor_id','route','container_type','price','currency','last_updated'];
    protected $casts = ['last_updated' => 'date', 'price' => 'decimal:0'];

    public function vendor(): BelongsTo { return $this->belongsTo(Vendor::class); }
}
