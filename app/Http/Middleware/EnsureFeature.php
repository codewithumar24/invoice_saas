<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/** Route middleware "feature:inventory": module must be enabled, allowed by the plan and not flagged off. */
class EnsureFeature
{
    public function handle(Request $request, Closure $next, string $feature)
    {
        if (! feature($feature)) {
            if ($request->expectsJson()) {
                abort(403, __('This feature is not available on your plan.'));
            }

            return redirect()->route('dashboard')->with('error', __('That feature is not enabled for this workspace. Switch it on in Settings or upgrade your plan.'));
        }

        return $next($request);
    }
}
