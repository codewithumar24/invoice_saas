<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Subscription extends TenantModel
{
    public const STATUSES = ['trial', 'active', 'past_due', 'cancelled', 'expired'];

    protected function casts(): array
    {
        return [
            'trial_ends_at' => 'datetime', 'current_period_start' => 'datetime', 'current_period_end' => 'datetime',
            'cancelled_at' => 'datetime', 'ends_at' => 'datetime',
        ];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    public function isUsable(): bool
    {
        return in_array($this->status, ['trial', 'active', 'past_due'], true)
            || ($this->status === 'cancelled' && $this->ends_at && $this->ends_at->isFuture());
    }
}
