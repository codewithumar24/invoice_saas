<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class QuotePolicy extends TenantPolicy
{
    protected string $group = 'quotes';

    public function send(User $user, Model $model): bool
    {
        return $this->allows($user, 'send', $model);
    }
}
