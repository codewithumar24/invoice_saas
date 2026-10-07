<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'is_public' => 'boolean'];
    }

    public function features(): HasMany
    {
        return $this->hasMany(PlanFeature::class);
    }

    /** Raw value of a plan feature/limit, or null when the plan does not define it. */
    public function featureValue(string $key): ?string
    {
        return $this->features->firstWhere('key', $key)?->value;
    }
}
