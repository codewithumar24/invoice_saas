<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class InvoicePolicy extends TenantPolicy
{
    protected string $group = 'invoices';

    public function send(User $user, Model $model): bool
    {
        return $this->allows($user, 'send', $model);
    }

    public function download(User $user, Model $model): bool
    {
        return $this->allows($user, 'download', $model);
    }

    public function duplicate(User $user, Model $model): bool
    {
        return $this->allows($user, 'duplicate', $model);
    }
}
