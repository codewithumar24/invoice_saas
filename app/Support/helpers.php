<?php

use App\Models\Tenant;
use App\Services\FeatureService;
use App\Services\SettingsService;
use App\Support\Money;
use App\Support\TenantContext;
use Illuminate\Support\Carbon;

if (! function_exists('tenant')) {
    /** The current workspace, or null outside of a workspace. */
    function tenant(): ?Tenant
    {
        return app(TenantContext::class)->get();
    }
}

if (! function_exists('money')) {
    /** Format an amount in its own currency. The workspace chooses symbol ($1,250.00) or code (USD 1,250.00). */
    function money(string|int|float|null $amount, ?string $currency = null, bool $withCode = false): string
    {
        $withCode = $withCode || (app(TenantContext::class)->check() && setting('currency_display') === 'code');

        return Money::format($amount, $currency, $withCode);
    }
}

if (! function_exists('setting')) {
    /** Read a tenant setting (falls back to the default from config/saas.php). */
    function setting(string $key, mixed $default = null): mixed
    {
        return app(SettingsService::class)->get($key, $default);
    }
}

if (! function_exists('platform_setting')) {
    function platform_setting(string $key, mixed $default = null): mixed
    {
        return app(SettingsService::class)->platform($key, $default);
    }
}

if (! function_exists('feature')) {
    /** Is a feature available to the current workspace (flag + plan + enabled modules)? */
    function feature(string $key): bool
    {
        return app(FeatureService::class)->enabled($key);
    }
}

if (! function_exists('tdate')) {
    /** Format a date/time in the workspace timezone. */
    function tdate(mixed $value, string $format = 'd M Y'): string
    {
        if (! $value) {
            return '—';
        }

        $date = $value instanceof DateTimeInterface ? Carbon::instance($value) : Carbon::parse($value);

        return $date->timezone(app(TenantContext::class)->timezone())->format($format);
    }
}

if (! function_exists('today_local')) {
    /** "Today" in the workspace timezone (financial dates must not follow the server timezone). */
    function today_local(): Carbon
    {
        return Carbon::now(app(TenantContext::class)->timezone())->startOfDay();
    }
}

if (! function_exists('tenant_exists')) {
    /** Validation rule: the id must exist in the given table AND belong to the current workspace. */
    function tenant_exists(string $table, string $column = 'id'): \Illuminate\Validation\Rules\Exists
    {
        return \Illuminate\Validation\Rule::exists($table, $column)->where('tenant_id', app(TenantContext::class)->id());
    }
}

if (! function_exists('status_label')) {
    function status_label(?string $status): string
    {
        return __(ucfirst(str_replace('_', ' ', (string) $status)));
    }
}
