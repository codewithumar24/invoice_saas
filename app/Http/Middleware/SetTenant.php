<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Support\TenantContext;
use Closure;
use Illuminate\Http\Request;

/** Resolves the workspace of the request (custom domain first, then the user's current workspace). */
class SetTenant
{
    public function __construct(private TenantContext $context) {}

    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        // Only workspaces where the user is an active member
        $memberships = $user->tenants()->wherePivot('status', 'active');

        // White-label custom domain: app.clientdomain.com maps to exactly one workspace
        $domainTenantId = Tenant::where('custom_domain', $request->getHost())->value('id');

        $tenant = (clone $memberships)->where('tenants.id', $domainTenantId ?: $user->current_tenant_id)->first();

        if (! $tenant && ! $domainTenantId) {
            $tenant = (clone $memberships)->where('tenants.status', 'active')->first() ?? $memberships->first();
        }

        if (! $tenant) {
            return redirect()->route('workspaces.create');
        }

        if (! $tenant->isActive()) {
            abort(403, __('This workspace is suspended. Please contact support.'));
        }

        if ($user->current_tenant_id !== $tenant->id) {
            $user->forceFill(['current_tenant_id' => $tenant->id])->save();
        }

        $this->context->set($tenant);
        view()->share('currentTenant', $tenant);

        return $next($request);
    }
}
