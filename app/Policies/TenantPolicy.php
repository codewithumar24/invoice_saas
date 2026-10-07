<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Base policy: Tenant + User + Permission + Resource.
 * The record must belong to the current workspace and the user must hold the database-driven permission.
 */
abstract class TenantPolicy
{
    /** Permission group, e.g. "invoices". */
    protected string $group;

    protected function allows(User $user, string $action, ?Model $model = null): bool
    {
        if ($model && $model->tenant_id !== tenant()?->id) {
            return false;
        }

        return $user->hasPermission("{$this->group}.{$action}");
    }

    public function viewAny(User $user): bool
    {
        return $this->allows($user, 'view');
    }

    public function view(User $user, Model $model): bool
    {
        return $this->allows($user, 'view', $model);
    }

    public function create(User $user): bool
    {
        return $this->allows($user, 'create');
    }

    public function update(User $user, Model $model): bool
    {
        return $this->allows($user, 'update', $model);
    }

    public function delete(User $user, Model $model): bool
    {
        return $this->allows($user, 'delete', $model);
    }
}
