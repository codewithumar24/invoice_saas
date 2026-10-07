<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Export extends TenantModel
{
    protected function casts(): array
    {
        return ['filters' => 'array', 'completed_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
