<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/** When a workspace requires 2FA, members without it can only reach their security settings. */
class RequireTwoFactorSetup
{
    public function handle(Request $request, Closure $next)
    {
        if (tenant() && setting('two_factor_required') && ! $request->user()->hasTwoFactor()
            && ! $request->routeIs('account.*', 'two-factor.*', 'logout', 'workspaces.*')) {
            return redirect()->route('account.security')->with('error', __('This workspace requires two-factor authentication. Please set it up to continue.'));
        }

        return $next($request);
    }
}
