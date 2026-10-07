<?php

namespace App\Models;

class TaxRate extends TenantModel
{
    protected function casts(): array
    {
        return ['is_default' => 'boolean', 'is_active' => 'boolean'];
    }
}
