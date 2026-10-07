<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryLocation extends TenantModel
{
    protected function casts(): array
    {
        return ['is_default' => 'boolean'];
    }

    public function stock(): HasMany
    {
        return $this->hasMany(InventoryStock::class, 'location_id');
    }
}
