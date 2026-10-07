<?php

namespace App\Services;

use App\Models\FeatureFlag;
use App\Models\Plan;
use App\Models\PlanFeature;
use App\Support\TenantContext;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

/**
 * Answers "may this workspace use X?" from three database-driven sources:
 * platform feature flags, the workspace plan (plan_features) and the modules the workspace switched on.
 */
class FeatureService
{
    public function __construct(private TenantContext $context) {}

    public function enabled(string $key): bool
    {
        $tenant = $this->context->get();

        if (! $tenant) {
            return false;
        }

        if (! $this->flagAllows($key, $tenant->id)) {
            return false;
        }

        // Plan check: only for keys that are sold as plan features
        if (in_array($key, config('saas.features'), true)) {
            $plan = $this->plan($tenant->plan_id);

            if ($plan !== null && ($plan[$key] ?? null) !== '1') {
                return false;
            }
        }

        // Module check: the workspace must have the module switched on
        if (array_key_exists($key, config('saas.modules'))) {
            return $tenant->hasModule($key);
        }

        // time tracking belongs to the projects module
        if ($key === 'time_tracking') {
            return $tenant->hasModule('projects');
        }

        return true;
    }

    /** Numeric plan limit; null means unlimited. */
    public function limit(string $key): ?int
    {
        $tenant = $this->context->get();
        $plan = $tenant ? $this->plan($tenant->plan_id) : null;
        $value = $plan[$key] ?? null;

        if ($value === null || $value === '' || $value === 'unlimited' || (int) $value < 0) {
            return null;
        }

        return (int) $value;
    }

    public function withinLimit(string $key, int $currentCount): bool
    {
        $limit = $this->limit($key);

        return $limit === null || $currentCount < $limit;
    }

    /** Stop a create action when the plan limit is reached. */
    public function enforceLimit(string $key, int $currentCount, string $label): void
    {
        if (! $this->withinLimit($key, $currentCount)) {
            throw ValidationException::withMessages([
                'limit' => __('Your plan allows :limit :label. Upgrade your plan to add more.', ['limit' => $this->limit($key), 'label' => $label]),
            ]);
        }
    }

    private function flagAllows(string $key, int $tenantId): bool
    {
        $flags = Cache::remember('feature_flags', 600, fn () => FeatureFlag::all()->keyBy('key')->map(
            fn ($flag) => ['enabled' => $flag->enabled, 'overrides' => $flag->tenant_overrides ?? []]
        )->all());

        if (! isset($flags[$key])) {
            return true;
        }

        return (bool) ($flags[$key]['overrides'][(string) $tenantId] ?? $flags[$key]['enabled']);
    }

    /** [feature key => value] of a plan, cached as a plain array. Null when the workspace has no plan. */
    private function plan(?int $planId): ?array
    {
        if (! $planId) {
            return null;
        }

        return Cache::remember("plan:{$planId}", 600, fn () => PlanFeature::where('plan_id', $planId)->pluck('value', 'key')->all());
    }

    public static function flush(): void
    {
        Cache::forget('feature_flags');
        Plan::pluck('id')->each(fn ($id) => Cache::forget("plan:{$id}"));
    }
}
