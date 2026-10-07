<?php

namespace App\Models;

class InvoiceView extends TenantModel
{
    public $timestamps = false;

    protected function casts(): array
    {
        return ['viewed_at' => 'datetime'];
    }
}
