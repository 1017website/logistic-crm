<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Lead extends Model
{
    use SoftDeletes;
    protected $fillable = [
        'lead_code','customer_id','company_name','pic_name','pic_position',
        'phone','email','address','industry','location','pipeline_stage','temperature',
        'product_interest','volume_estimate','timeline','notes_kebutuhan',
        'catatan_internal','probability','lead_score',
        'lead_source','competitor','expected_closing','user_id',
        'next_follow_up','next_follow_up_time','next_follow_up_notes'
    ];

    protected $casts = [
        'expected_closing' => 'date',
        'next_follow_up'   => 'date',
        'lead_score'       => 'decimal:1',
    ];

    public function salesUser(): BelongsTo  { return $this->belongsTo(User::class, 'user_id'); }
    public function user(): BelongsTo       { return $this->belongsTo(User::class, 'user_id'); }
    public function customer(): BelongsTo   { return $this->belongsTo(Customer::class); }
    public function activities(): HasMany   { return $this->hasMany(Activity::class); }
    public function deliveryOrders(): HasMany { return $this->hasMany(DeliveryOrder::class); }
    public function products(): HasMany     { return $this->hasMany(LeadProduct::class); }
    public function pics(): HasMany         { return $this->hasMany(LeadPic::class); }
    public function primaryPic(): HasMany   { return $this->hasMany(LeadPic::class)->where('is_primary', true); }

    public function getPipelineStageColorAttribute(): string
    {
        return match($this->pipeline_stage) {
            'Identifying' => 'primary',
            'Approaching' => 'warning',
            'Follow Up'   => 'purple',
            'Closing'     => 'success',
            'Won'         => 'teal',
            'Lost'        => 'danger',
            'Maintaining' => 'indigo',
            default       => 'secondary',
        };
    }

    public static function generateLeadCode(): string
    {
        $prefix = 'LEAD-' . date('Y') . '-';
        $last   = static::where('lead_code', 'like', $prefix . '%')
            ->orderByDesc('lead_code')->value('lead_code');
        $seq    = $last ? (intval(substr($last, -4)) + 1) : 1;
        return $prefix . str_pad($seq, 4, '0', STR_PAD_LEFT);
    }
}
