<?php

namespace App\Models;

class ExchangeRate extends TenantModel
{
    protected function casts(): array
    {
        return ['effective_date' => \App\Casts\DateOnly::class];
    }
}
