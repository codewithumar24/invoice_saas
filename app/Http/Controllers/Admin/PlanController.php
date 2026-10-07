<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use App\Models\FeatureFlag;
use App\Models\Plan;
use App\Services\AuditService;
use App\Services\FeatureService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/** Plans with their database-driven limits and features, coupons and feature flags. */
class PlanController extends Controller
{
    public function index()
    {
        return view('admin.plans.index', ['plans' => Plan::with('features')->withCount(['features'])->orderBy('sort_order')->get()]);
    }

    public function create()
    {
        return view('admin.plans.form', ['plan' => new Plan(['currency' => 'USD', 'is_active' => true, 'is_public' => true]), 'values' => []]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $plan = Plan::create($data['plan'] + ['slug' => $this->slug($data['plan']['name'])]);
        $this->syncFeatures($plan, $data);

        return redirect()->route('admin.plans.index')->with('success', __('Plan created.'));
    }

    public function edit(Plan $plan)
    {
        return view('admin.plans.form', ['plan' => $plan, 'values' => $plan->features()->pluck('value', 'key')->all()]);
    }

    public function update(Request $request, Plan $plan): RedirectResponse
    {
        $data = $this->validated($request);
        $plan->update($data['plan']);
        $this->syncFeatures($plan, $data);
        app(AuditService::class)->log('admin.plan_updated', $plan->name);

        return redirect()->route('admin.plans.index')->with('success', __('Plan saved. The new limits apply to every workspace on this plan.'));
    }

    public function destroy(Plan $plan): RedirectResponse
    {
        if (\App\Models\Tenant::where('plan_id', $plan->id)->exists()) {
            $plan->update(['is_active' => false, 'is_public' => false]);

            return back()->with('success', __('Workspaces still use this plan, so it was hidden instead of deleted.'));
        }

        $plan->delete();
        FeatureService::flush();

        return back()->with('success', __('Plan deleted.'));
    }

    private function validated(Request $request): array
    {
        $plan = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:255'],
            'price_monthly' => ['required', 'numeric', 'min:0'],
            'price_yearly' => ['required', 'numeric', 'min:0'],
            'currency' => ['required', Rule::in(array_keys(config('currencies')))],
            'trial_days' => ['required', 'integer', 'min:0', 'max:365'],
            'sort_order' => ['required', 'integer', 'min:0'],
        ]);

        $extra = $request->validate([
            'limits' => ['nullable', 'array'], 'limits.*' => ['nullable', 'string', 'regex:/^(unlimited|\d+)?$/'],
            'features' => ['nullable', 'array'], 'features.*' => ['nullable', 'boolean'],
        ]);

        return ['plan' => $plan + ['is_active' => $request->boolean('is_active'), 'is_public' => $request->boolean('is_public')]] + $extra;
    }

    private function syncFeatures(Plan $plan, array $data): void
    {
        foreach (config('saas.limits') as $key) {
            $value = $data['limits'][$key] ?? '';
            $plan->features()->updateOrCreate(['key' => $key], ['type' => 'limit', 'value' => $value === '' ? 'unlimited' : $value]);
        }

        foreach (config('saas.features') as $key) {
            $plan->features()->updateOrCreate(['key' => $key], ['type' => 'boolean', 'value' => ! empty($data['features'][$key]) ? '1' : '0']);
        }

        FeatureService::flush();
    }

    private function slug(string $name): string
    {
        $slug = Str::slug($name);

        return Plan::where('slug', $slug)->exists() ? $slug.'-'.Str::lower(Str::random(4)) : $slug;
    }

    /* ------------------------------ Coupons ------------------------------ */

    public function coupons()
    {
        return view('admin.coupons', ['coupons' => Coupon::with('plan')->latest()->paginate(25), 'plans' => Plan::orderBy('sort_order')->get()]);
    }

    public function storeCoupon(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:50', 'regex:/^[A-Za-z0-9_-]+$/', 'unique:coupons,code'],
            'type' => ['required', Rule::in(['percent', 'fixed', 'first_month', 'trial_extension'])],
            'value' => ['required', 'numeric', 'min:0', 'max:100000'],
            'plan_id' => ['nullable', 'exists:plans,id'],
            'max_uses' => ['nullable', 'integer', 'min:1'],
            'expires_at' => ['nullable', 'date', 'after:today'],
        ]);

        if (in_array($data['type'], ['percent', 'first_month'], true) && $data['value'] > 100) {
            return back()->withErrors(['value' => __('A percentage discount cannot be more than 100.')])->withInput();
        }

        Coupon::create(['code' => strtoupper($data['code'])] + $data);

        return back()->with('success', __('Coupon created.'));
    }

    public function toggleCoupon(Coupon $coupon): RedirectResponse
    {
        $coupon->update(['is_active' => ! $coupon->is_active]);

        return back()->with('success', $coupon->is_active ? __('Coupon switched on.') : __('Coupon switched off.'));
    }

    public function destroyCoupon(Coupon $coupon): RedirectResponse
    {
        $coupon->delete();

        return back()->with('success', __('Coupon deleted.'));
    }

    /* ------------------------------ Feature flags ------------------------------ */

    public function flags()
    {
        // Make sure every known feature has a flag row, so new features can be rolled out gradually
        foreach (array_unique(array_merge(config('saas.features'), array_keys(config('saas.modules')))) as $key) {
            FeatureFlag::firstOrCreate(['key' => $key], ['name' => Str::headline($key), 'enabled' => true]);
        }

        return view('admin.flags', ['flags' => FeatureFlag::orderBy('key')->get()]);
    }

    public function updateFlag(Request $request, FeatureFlag $flag): RedirectResponse
    {
        $data = $request->validate([
            'enabled' => ['nullable', 'boolean'],
            'allow_tenants' => ['nullable', 'string', 'max:1000', 'regex:/^[\d,\s]*$/'],
            'deny_tenants' => ['nullable', 'string', 'max:1000', 'regex:/^[\d,\s]*$/'],
        ]);

        // Per-workspace overrides: enable early for some, or switch off for some
        $overrides = [];
        foreach (array_filter(array_map('trim', explode(',', (string) ($data['allow_tenants'] ?? '')))) as $id) {
            $overrides[$id] = true;
        }
        foreach (array_filter(array_map('trim', explode(',', (string) ($data['deny_tenants'] ?? '')))) as $id) {
            $overrides[$id] = false;
        }

        $flag->update(['enabled' => $request->boolean('enabled'), 'tenant_overrides' => $overrides ?: null]);
        FeatureService::flush();
        app(AuditService::class)->log('admin.feature_flag_updated', $flag->key, ['enabled' => $flag->enabled]);

        return back()->with('success', __('Feature flag saved.'));
    }
}
