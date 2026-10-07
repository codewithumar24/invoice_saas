<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupportTicket extends TenantModel
{
    protected function casts(): array
    {
        return ['last_reply_at' => 'datetime'];
    }

    public function messages(): HasMany
    {
        return $this->hasMany(SupportMessage::class)->oldest();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
