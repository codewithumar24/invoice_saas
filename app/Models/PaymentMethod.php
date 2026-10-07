<?php

namespace App\Models;

class PaymentMethod extends TenantModel
{
    protected $hidden = ['config'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'config' => 'encrypted:array'];
    }

    public function isOnline(): bool
    {
        return in_array($this->type, ['stripe', 'paypal', 'custom'], true);
    }
}
