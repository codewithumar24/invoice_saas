<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/** Locale order: user preference, then workspace, then session (public site), then app default. */
class SetLocale
{
    public function handle(Request $request, Closure $next)
    {
        $locale = $request->user()?->locale
            ?: ($request->hasSession() ? $request->session()->get('locale') : null)
            ?: tenant()?->locale;

        if ($locale && array_key_exists($locale, config('saas.locales'))) {
            app()->setLocale($locale);
        }

        view()->share('isRtl', in_array(app()->getLocale(), config('saas.rtl_locales'), true));

        return $next($request);
    }
}
