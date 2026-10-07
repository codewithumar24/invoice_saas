<?php

namespace App\Models;

class BusinessMetric extends TenantModel
{
    protected function casts(): array
    {
        return ['metric_date' => \App\Casts\DateOnly::class];
    }
}
