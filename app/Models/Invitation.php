<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Invitation extends TenantModel
{
    protected function casts(): array
    {
        return ['accepted_at' => 'datetime', 'expires_at' => 'datetime'];
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    public function isPending(): bool
    {
        return $this->accepted_at === null && $this->expires_at->isFuture();
    }
}
