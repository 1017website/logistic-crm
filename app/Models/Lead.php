<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Lead extends Model
{
    protected $fillable = [
        'lead_code','customer_id','company_name','pic_name','pic_position','phone','email',
        'address','industry','pipeline_stage','temperature','service_type','route',
        'commodity','volume_estimate','timeline','notes_kebutuhan','catatan_internal',
        'potensi_revenue','probability','lead_score','lead_source','competitor',
        'expected_closing','user_id','sales_user_id','next_follow_up','next_follow_up_time','next_follow_up_notes'
    ];

    protected $casts = [
        'expected_closing' => 'date',
        'next_follow_up' => 'date',
        'potensi_revenue' => 'decimal:0',
    ];

    public function customer(): BelongsTo  { return $this->belongsTo(Customer::class); }
    public function user(): BelongsTo      { return $this->belongsTo(User::class, 'user_id'); }
    public function salesUser(): BelongsTo { return $this->belongsTo(User::class, 'user_id'); } // backward compat
    public function activities(): HasMany  { return $this->hasMany(Activity::class); }
    public function quotations(): HasMany  { return $this->hasMany(Quotation::class); }

    public function getTemperatureColorAttribute(): string
    {
        return match($this->temperature) {
            'Hot' => 'danger',
            'Warm' => 'warning',
            'Cold' => 'info',
            default => 'secondary',
        };
    }

    public function getPipelineStageColorAttribute(): string
    {
        return match($this->pipeline_stage) {
            'Identifying' => 'primary',
            'Approaching' => 'warning',
            'Follow Up' => 'purple',
            'Closing' => 'success',
            'Won' => 'teal',
            'Lost' => 'danger',
            default => 'secondary',
        };
    }

    public static function generateLeadCode(): string
    {
        $year = date('Y');
        $last = static::where('lead_code', 'like', "LEAD-{$year}-%")->orderBy('id', 'desc')->first();
        $num = $last ? (int) substr($last->lead_code, -4) + 1 : 1;
        return "LEAD-{$year}-" . str_pad($num, 4, '0', STR_PAD_LEFT);
    }

    public function getLogoInitialsAttribute(): string
    {
        $parts = explode(' ', $this->company_name);
        return strtoupper(substr($parts[0], 0, 1) . (isset($parts[1]) ? substr($parts[1], 0, 1) : substr($parts[0], 1, 1)));
    }
}
