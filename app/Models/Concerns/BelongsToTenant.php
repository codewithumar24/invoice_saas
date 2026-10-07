<?php

namespace App\Models\Concerns;

use App\Models\Tenant;
use App\Support\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tenant isolation for a model. Every query is limited to the current workspace
 * and new records are stamped with the current workspace id.
 */
trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope('tenant', function (Builder $builder) {
            $context = app(TenantContext::class);

            if ($context->check()) {
                $builder->where($builder->getModel()->qualifyColumn('tenant_id'), $context->id());
            } else {
                // Fail closed: without a workspace no rows are returned, never all rows.
                $builder->whereRaw('1 = 0');
            }
        });

        static::creating(function ($model) {
            if (! $model->tenant_id) {
                $model->tenant_id = app(TenantContext::class)->id();
            }
        });
    }

    /** Explicit opt-out for platform admin code and cross-tenant jobs. */
    public function scopeAllTenants(Builder $query): Builder
    {
        return $query->withoutGlobalScope('tenant');
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
