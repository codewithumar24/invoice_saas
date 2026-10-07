<?php

namespace App\Providers;

use App\Listeners\BusinessEventSubscriber;
use App\Models\Announcement;
use App\Support\TenantContext;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // "scoped" = one instance per request / queue job, reset in between
        $this->app->scoped(TenantContext::class);
        $this->app->scoped(\App\Services\SettingsService::class);
    }

    public function boot(): void
    {
        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }

        Event::subscribe(BusinessEventSubscriber::class);
        Paginator::defaultView('partials.pagination');
        Paginator::defaultSimpleView('partials.pagination');

        Password::defaults(fn () => Password::min(8)->letters()->mixedCase()->numbers());

        /*
         | Authorization. Abilities written as "group.action" (invoices.create) are answered from the
         | database-driven role/permission tables. Everything else falls through to the model policies.
         */
        Gate::before(function ($user, string $ability) {
            return str_contains($ability, '.') ? $user->hasPermission($ability) : null;
        });

        $this->configureRateLimiting();
        $this->shareLayoutData();

        // Horizon dashboard (when installed) is for platform administrators only
        if (class_exists(\Laravel\Horizon\Horizon::class)) {
            \Laravel\Horizon\Horizon::auth(fn ($request) => (bool) $request->user()?->is_super_admin);
        }
    }

    private function configureRateLimiting(): void
    {
        $limits = config('saas.rate_limits');

        RateLimiter::for('login', fn (Request $request) => Limit::perMinute($limits['login'])->by(mb_strtolower((string) $request->input('email')).'|'.$request->ip()));
        RateLimiter::for('sensitive', fn (Request $request) => Limit::perMinute($limits['sensitive'])->by($request->user()?->id ?: $request->ip()));
        RateLimiter::for('public', fn (Request $request) => Limit::perMinute($limits['public'])->by($request->ip()));
        RateLimiter::for('webhooks', fn (Request $request) => Limit::perMinute(300)->by($request->ip()));
        RateLimiter::for('api', function (Request $request) use ($limits) {
            return Limit::perMinute($limits['api'])->by(hash('sha256', (string) $request->bearerToken()) ?: $request->ip())
                ->response(fn () => response()->json(['success' => false, 'message' => 'Too many requests.', 'errors' => (object) []], 429));
        });
    }

    /** Data every signed-in page needs: unread notifications, workspaces, announcements. */
    private function shareLayoutData(): void
    {
        View::composer('layouts.app', function ($view) {
            $user = auth()->user();
            $tenant = tenant();

            if (! $user || ! $tenant) {
                return;
            }

            $view->with([
                'unreadNotifications' => $user->unreadNotifications()->where('data', 'like', '%"tenant_id":'.$tenant->id.'}%')->limit(6)->get(),
                'workspaces' => $user->tenants()->wherePivot('status', 'active')->get(),
                'announcements' => Announcement::where('is_active', true)
                    ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
                    ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', now()))
                    ->latest()->get()->filter(fn ($a) => match ($a->target_type) {
                        'plans' => in_array($tenant->plan_id, array_map('intval', $a->target_values ?? []), true),
                        'countries' => in_array($tenant->country, $a->target_values ?? [], true),
                        'tenants' => in_array($tenant->id, array_map('intval', $a->target_values ?? []), true),
                        default => true,
                    })->reject(fn ($a) => in_array($a->id, session('dismissed_announcements', []), true))->take(2),
            ]);
        });
    }
}
