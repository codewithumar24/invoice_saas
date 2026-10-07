<?php

namespace App\Models;

class SubscriptionInvoice extends TenantModel
{
    protected function casts(): array
    {
        return ['paid_at' => 'datetime'];
    }
}
