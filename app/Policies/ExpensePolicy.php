<?php

namespace App\Policies;

class ExpensePolicy extends TenantPolicy
{
    protected string $group = 'expenses';
}
