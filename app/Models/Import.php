<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Import extends TenantModel
{
    protected function casts(): array
    {
        return ['mapping' => 'array', 'errors' => 'array'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
