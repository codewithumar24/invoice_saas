<?php

namespace App\Policies;

class ProductPolicy extends TenantPolicy
{
    protected string $group = 'products';
}
