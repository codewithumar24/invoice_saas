<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Coupon extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime', 'is_active' => 'boolean'];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function isRedeemable(?int $planId = null): bool
    {
        return $this->is_active
            && (! $this->expires_at || $this->expires_at->isFuture())
            && ($this->max_uses === null || $this->used_count < $this->max_uses)
            && (! $this->plan_id || $planId === null || $this->plan_id === $planId);
    }
}
