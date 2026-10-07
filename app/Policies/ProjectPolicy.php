<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class ProjectPolicy extends TenantPolicy
{
    protected string $group = 'projects';

    public function create(User $user): bool
    {
        return $this->allows($user, 'manage');
    }

    public function update(User $user, Model $model): bool
    {
        return $this->allows($user, 'manage', $model);
    }

    public function delete(User $user, Model $model): bool
    {
        return $this->allows($user, 'manage', $model);
    }
}
