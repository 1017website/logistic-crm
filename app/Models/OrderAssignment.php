<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Penugasan armada untuk sebuah Request DO — dibuat Sales Admin atau
 * Transport Planner, lalu disetujui pada tahap Approval Penugasan.
 */
class OrderAssignment extends Model
{
    protected $fillable = [
        'request_order_id', 'assignment_type', 'vendor_id',
        'fleet_info', 'driver_name', 'driver_phone', 'estimated_cost',
        'planned_by', 'approval_status', 'approved_by', 'approved_at',
        'approval_note', 'notes',
    ];

    protected $casts = [
        'estimated_cost' => 'decimal:0',
        'approved_at'    => 'datetime',
    ];

    public function requestOrder(): BelongsTo { return $this->belongsTo(RequestOrder::class); }
    public function vendor(): BelongsTo       { return $this->belongsTo(Vendor::class); }
    public function planner(): BelongsTo      { return $this->belongsTo(User::class, 'planned_by'); }
    public function approver(): BelongsTo     { return $this->belongsTo(User::class, 'approved_by'); }

    public function isInternal(): bool { return $this->assignment_type === 'internal'; }
    public function isExternal(): bool { return $this->assignment_type === 'external'; }
}
