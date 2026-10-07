<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Attachment extends TenantModel
{
    protected function casts(): array
    {
        return ['visible_to_customer' => 'boolean'];
    }

    public function attachable(): \Illuminate\Database\Eloquent\Relations\MorphTo
    {
        return $this->morphTo();
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
