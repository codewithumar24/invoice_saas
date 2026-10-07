<?php

namespace App\Models;

class CustomerContact extends TenantModel
{
    protected function casts(): array
    {
        return ['is_primary' => 'boolean'];
    }
}
