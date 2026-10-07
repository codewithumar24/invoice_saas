<?php

namespace App\Http\Middleware;

use App\Models\Customer;
use App\Models\Tenant;
use App\Support\TenantContext;
use Closure;
use Illuminate\Http\Request;

/** Customer portal: resolves the business from the URL and the signed-in customer from the session. */
class PortalAuth
{
    public function __construct(private TenantContext $context) {}

    public function handle(Request $request, Closure $next, string $mode = 'auth')
    {
        $tenant = $request->route('tenant');
        $tenant = $tenant instanceof Tenant ? $tenant : Tenant::where('slug', $tenant)->first();

        abort_unless($tenant && $tenant->isActive(), 404);

        $this->context->set($tenant);
        abort_unless(feature('customer_portal') && setting('portal_enabled'), 404);

        app()->setLocale($tenant->locale ?: config('app.locale'));
        view()->share(['portalTenant' => $tenant, 'isRtl' => in_array(app()->getLocale(), config('saas.rtl_locales'), true)]);
        $request->route()->forgetParameter('tenant');

        if ($mode === 'guest') {
            return $next($request);
        }

        $session = $request->session()->get('portal');
        $customer = ($session['tenant_id'] ?? null) === $tenant->id ? Customer::where('portal_enabled', true)->find($session['customer_id']) : null;

        if (! $customer) {
            return redirect()->route('portal.login', $tenant->slug);
        }

        $request->attributes->set('portal_customer', $customer);
        view()->share('portalCustomer', $customer);

        return $next($request);
    }
}
