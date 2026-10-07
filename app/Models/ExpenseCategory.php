<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

class ExpenseCategory extends TenantModel
{
    protected function casts(): array
    {
        return ['is_system' => 'boolean'];
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class, 'category_id');
    }
}
