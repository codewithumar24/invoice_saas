<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Quote extends TenantModel
{
    use Auditable, SoftDeletes;

    public const STATUSES = ['draft', 'sent', 'viewed', 'accepted', 'rejected', 'expired', 'converted'];

    protected $hidden = ['public_token'];

    protected function casts(): array
    {
        return [
            'issue_date' => \App\Casts\DateOnly::class,
            'expiry_date' => \App\Casts\DateOnly::class,
            'tax_inclusive' => 'boolean',
            'sent_at' => 'datetime',
            'viewed_at' => 'datetime',
            'accepted_at' => 'datetime',
            'rejected_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class)->withTrashed();
    }

    public function items(): HasMany
    {
        return $this->hasMany(QuoteItem::class)->orderBy('sort_order');
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'converted_invoice_id');
    }

    public function activities(): MorphMany
    {
        return $this->morphMany(ActivityLog::class, 'subject')->latest();
    }

    public function isExpired(): bool
    {
        return $this->expiry_date && in_array($this->status, ['sent', 'viewed'], true) && $this->expiry_date->lt(today_local());
    }

    public function displayStatus(): string
    {
        return $this->isExpired() ? 'expired' : $this->status;
    }

    public function canRespond(): bool
    {
        return in_array($this->status, ['sent', 'viewed'], true) && ! $this->isExpired();
    }

    public function publicUrl(): string
    {
        return route('public.quote.show', $this->public_token);
    }
}
