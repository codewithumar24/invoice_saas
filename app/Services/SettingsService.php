<?php

namespace App\Services;

use App\Models\Setting;
use App\Support\TenantContext;
use Illuminate\Support\Facades\Cache;

class SettingsService
{
    /** Per-request memory so settings are loaded once per workspace. */
    private array $loaded = [];

    public function __construct(private TenantContext $context) {}

    public function get(string $key, mixed $default = null): mixed
    {
        $values = $this->all($this->context->id());

        return $values[$key] ?? $default ?? config("saas.defaults.{$key}");
    }

    public function platform(string $key, mixed $default = null): mixed
    {
        return $this->all(null)[$key] ?? $default;
    }

    /** Save several workspace settings at once. */
    public function set(array $values): void
    {
        $this->write($this->context->id(), $values);
    }

    public function setPlatform(array $values): void
    {
        $this->write(null, $values);
    }

    private function all(?int $tenantId): array
    {
        $cacheKey = 'settings:'.($tenantId ?? 'platform');

        return $this->loaded[$cacheKey] ??= Cache::remember($cacheKey, 3600, function () use ($tenantId) {
            return Setting::query()
                ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId), fn ($q) => $q->whereNull('tenant_id'))
                ->pluck('value', 'key')
                ->map(fn ($value) => json_decode((string) $value, true))
                ->all();
        });
    }

    private function write(?int $tenantId, array $values): void
    {
        foreach ($values as $key => $value) {
            Setting::updateOrCreate(['tenant_id' => $tenantId, 'key' => $key], ['value' => json_encode($value)]);
        }

        $cacheKey = 'settings:'.($tenantId ?? 'platform');
        Cache::forget($cacheKey);
        unset($this->loaded[$cacheKey]);
    }
}
