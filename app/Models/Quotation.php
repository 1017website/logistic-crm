<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Quotation extends Model
{
    public const STATUSES = [
        'draft' => 'Draft',
        'sent' => 'Terkirim',
        'accepted' => 'Disetujui',
        'rejected' => 'Ditolak',
    ];

    protected $fillable = [
        'quotation_number',
        'customer_id',
        'user_id',
        'quotation_date',
        'recipient_name',
        'recipient_title',
        'company_name',
        'recipient_address',
        'attachment',
        'subject',
        'opening',
        'terms',
        'closing',
        'contact_name',
        'contact_phone',
        'city',
        'signatory_name',
        'signatory_title',
        'status',
    ];

    protected $casts = [
        'quotation_date' => 'date',
        'terms' => 'array',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(QuotationItem::class)->orderBy('sort_order');
    }

    public function resolvedSignatoryTitle(): string
    {
        $signatoryName = trim((string) $this->signatory_name);
        $storedTitle = trim((string) $this->signatory_title);

        if ($signatoryName === '') {
            return $storedTitle;
        }

        $owner = $this->relationLoaded('user')
            ? $this->getRelation('user')
            : $this->user()->first();

        if ($owner && strcasecmp(trim((string) $owner->name), $signatoryName) === 0) {
            $position = trim((string) $owner->position);

            if ($position !== '') {
                return $position;
            }
        }

        $position = User::query()
            ->where('name', $signatoryName)
            ->whereNotNull('position')
            ->value('position');

        return trim((string) $position) ?: $storedTitle;
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status] ?? ucfirst($this->status);
    }

    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'sent' => 'primary',
            'accepted' => 'success',
            'rejected' => 'danger',
            default => 'secondary',
        };
    }
}
