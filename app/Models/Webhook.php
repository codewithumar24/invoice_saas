<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

class Webhook extends TenantModel
{
    protected $hidden = ['secret'];

    protected function casts(): array
    {
        return ['events' => 'array', 'is_active' => 'boolean'];
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(WebhookDelivery::class)->latest();
    }
}
