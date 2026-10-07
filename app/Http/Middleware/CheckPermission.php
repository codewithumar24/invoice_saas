<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/** Server-side permission check: route middleware "permission:invoices.view". */
class CheckPermission
{
    public function handle(Request $request, Closure $next, string ...$permissions)
    {
        // Any one of the listed permissions is enough
        foreach ($permissions as $permission) {
            if ($request->user()?->hasPermission($permission)) {
                return $next($request);
            }
        }

        abort(403, __('You do not have permission to do this.'));
    }
}
