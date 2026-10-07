<?php

namespace App\Models;

class CustomerAddress extends TenantModel
{
    public function oneLine(): string
    {
        return collect([$this->line1, $this->line2, $this->city, $this->state, $this->postal_code, $this->country])->filter()->implode(', ');
    }
}
