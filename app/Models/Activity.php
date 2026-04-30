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

    protected $casts = [
        'activity_at'    => 'datetime',
        'next_follow_up' => 'date',
    ];

    public function lead(): BelongsTo     { return $this->belongsTo(Lead::class); }
    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }
    public function salesUser(): BelongsTo { return $this->belongsTo(SalesUser::class); }

    public function getTypeIconAttribute(): string
    {
        return match($this->type) {
            'Call'  => 'phone',
            'Visit' => 'building',
            'Email' => 'envelope',
            'Note'  => 'sticky-note',
            default => 'clock',
        };
    }

    public function getTypeColorAttribute(): string
    {
        return match($this->type) {
            'Call'  => 'success',
            'Visit' => 'primary',
            'Email' => 'warning',
            'Note'  => 'info',
            default => 'secondary',
        };
    }

    public function getStatusColorAttribute(): string
    {
        return match($this->status) {
            'Done'    => 'success',
            'Pending' => 'warning',
            'Planned' => 'primary',
            'Overdue' => 'danger',
            default   => 'secondary',
        };
    }
}
