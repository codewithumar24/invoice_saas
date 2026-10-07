<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebhookDelivery extends TenantModel
{
    protected function casts(): array
    {
        return ['payload' => 'array', 'delivered_at' => 'datetime'];
    }

    public function webhook(): BelongsTo
    {
        return $this->belongsTo(Webhook::class);
    }
}
