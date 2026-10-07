<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class PaymentPolicy extends TenantPolicy
{
    protected string $group = 'payments';

    public function refund(User $user, Model $model): bool
    {
        return $this->allows($user, 'refund', $model);
    }
}
