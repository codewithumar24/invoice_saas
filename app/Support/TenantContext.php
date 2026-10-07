<?php

namespace App\Support;

use App\Models\Tenant;

/**
 * Holds the workspace (tenant) of the current request or queue job.
 * Registered as a scoped singleton, so it resets between requests and jobs.
 */
class TenantContext
{
    private ?Tenant $tenant = null;

    public function set(?Tenant $tenant): void
    {
        $this->tenant = $tenant;
    }

    public function get(): ?Tenant
    {
        return $this->tenant;
    }

    public function id(): ?int
    {
        return $this->tenant?->id;
    }

    public function check(): bool
    {
        return $this->tenant !== null;
    }

    public function timezone(): string
    {
        return $this->tenant?->timezone ?: config('app.timezone');
    }

    public function currency(): string
    {
        return $this->tenant?->currency ?: 'USD';
    }

    /** Run a callback inside another tenant, then restore the previous one. */
    public function run(Tenant $tenant, callable $callback): mixed
    {
        $previous = $this->tenant;
        $this->tenant = $tenant;

        try {
            return $callback($tenant);
        } finally {
            $this->tenant = $previous;
        }
    }
}
