<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Audit trail perpindahan status — polymorphic ke RequestOrder & DeliveryOrder.
 */
class OrderStatusLog extends Model
{
    protected $fillable = [
        'loggable_type', 'loggable_id', 'from_status', 'to_status', 'user_id', 'note',
    ];

    public function loggable(): MorphTo { return $this->morphTo(); }
    public function user(): BelongsTo   { return $this->belongsTo(User::class); }

    /** Buat entri log perpindahan status. */
    public static function record(Model $model, ?string $from, string $to, ?int $userId = null, ?string $note = null): self
    {
        return static::create([
            'loggable_type' => $model->getMorphClass(),
            'loggable_id'   => $model->getKey(),
            'from_status'   => $from,
            'to_status'     => $to,
            'user_id'       => $userId ?? auth()->id(),
            'note'          => $note,
        ]);
    }
}
