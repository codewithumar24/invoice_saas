<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/** Platform admin area: super admins only, optional IP allow-list, mandatory 2FA when the platform requires it. */
class SuperAdmin
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        // 404 rather than 403, so the admin area is not advertised to ordinary users
        abort_unless($user && $user->is_super_admin, 404);

        $allowed = array_filter(array_map('trim', explode(',', (string) platform_setting('admin_ip_allowlist', ''))));
        abort_if($allowed && ! in_array($request->ip(), $allowed, true), 403, __('The admin area is not available from this IP address.'));

        if (platform_setting('admin_require_2fa', false) && ! $user->hasTwoFactor() && ! $request->routeIs('account.security*', 'two-factor.*')) {
            return redirect()->route('account.security')->with('error', __('Platform administrators must enable two-factor authentication.'));
        }

        return $next($request);
    }
}
