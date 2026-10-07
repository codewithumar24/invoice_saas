<?php

namespace App\Models;

class BusinessSignal extends TenantModel
{
    protected function casts(): array
    {
        return ['data' => 'array', 'detected_on' => \App\Casts\DateOnly::class, 'dismissed_at' => 'datetime'];
    }
}
