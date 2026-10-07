<?php

namespace App\Jobs;

use App\Models\Tenant;
use App\Support\TenantContext;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Base class for jobs that work inside one workspace.
 * The worker has no request, so the job restores the tenant context itself before doing anything.
 */
abstract class TenantJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 120;

    public function __construct(public int $tenantId) {}

    public function handle(TenantContext $context): void
    {
        $tenant = Tenant::find($this->tenantId);

        if (! $tenant) {
            return;
        }

        $context->run($tenant, function () use ($tenant) {
            app()->setLocale($tenant->locale ?: config('app.locale'));
            app()->call([$this, 'process']);
        });
    }
}
