<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/** Maintenance mode switched on by a platform admin. Admins can still sign in and use the backend. */
class PlatformMaintenance
{
    public function handle(Request $request, Closure $next)
    {
        if (platform_setting('maintenance_mode', false)
            && ! $request->user()?->is_super_admin
            && ! $request->is('login', 'logout', 'two-factor*', 'admin*', 'webhooks/*', 'health', 'up')) {
            return response()->view('errors.maintenance', ['message' => platform_setting('maintenance_message')], 503);
        }

        return $next($request);
    }
}
