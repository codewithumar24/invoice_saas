<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/** Sends a new workspace through the onboarding steps before the app opens. */
class EnsureOnboarded
{
    public function handle(Request $request, Closure $next)
    {
        if (tenant() && ! tenant()->onboarded_at && $request->user()->isOwnerOf(tenant()->id)) {
            return redirect()->route('onboarding.show');
        }

        return $next($request);
    }
}
