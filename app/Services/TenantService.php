<?php

namespace App\Services;

use App\Models\ExpenseCategory;
use App\Models\InventoryLocation;
use App\Models\PaymentMethod;
use App\Models\Permission;
use App\Models\Referral;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TenantService
{
    public function __construct(private TenantContext $context, private SubscriptionService $subscriptions) {}

    /** Create a workspace with its default roles, categories and payment methods, owned by $owner. */
    public function create(User $owner, array $data, ?string $referralCode = null): Tenant
    {
        return DB::transaction(function () use ($owner, $data, $referralCode) {
            $tenant = Tenant::create([
                'name' => $data['name'],
                'slug' => $this->uniqueSlug($data['name']),
                'business_type' => $data['business_type'] ?? null,
                'country' => $data['country'] ?? $owner->country,
                'timezone' => $data['timezone'] ?? $owner->timezone ?? 'UTC',
                'currency' => $data['currency'] ?? 'USD',
                'email' => $owner->email,
                'enabled_modules' => config('saas.business_types.other.modules'),
                'referral_code' => strtoupper(Str::random(8)),
            ]);

            $tenant->users()->attach($owner->id, ['is_owner' => true, 'status' => 'active']);

            // Everything below is created inside the new workspace
            $this->context->run($tenant, function () use ($tenant, $owner) {
                $roles = $this->createDefaultRoles($tenant);
                DB::table('user_roles')->insert(['user_id' => $owner->id, 'role_id' => $roles['owner']->id, 'tenant_id' => $tenant->id]);

                foreach (config('saas.expense_categories') as $name) {
                    ExpenseCategory::create(['name' => $name, 'is_system' => true]);
                }

                PaymentMethod::create(['name' => 'Cash', 'type' => 'cash']);
                PaymentMethod::create(['name' => 'Bank transfer', 'type' => 'bank_transfer']);
                InventoryLocation::create(['name' => 'Main warehouse', 'type' => 'warehouse', 'is_default' => true]);
            });

            $this->subscriptions->startFor($tenant);

            if ($referralCode && ($referrer = Tenant::where('referral_code', strtoupper($referralCode))->first())) {
                $tenant->forceFill(['referred_by_tenant_id' => $referrer->id])->save();
                Referral::create(['referrer_tenant_id' => $referrer->id, 'referred_tenant_id' => $tenant->id]);
            }

            $owner->forceFill(['current_tenant_id' => $tenant->id])->save();

            return $tenant;
        });
    }

    /** @return array<string, Role> keyed by slug */
    public function createDefaultRoles(Tenant $tenant): array
    {
        self::syncPermissions();
        $all = Permission::pluck('id', 'name');
        $roles = [];

        foreach (config('permissions.roles') as $slug => $definition) {
            $role = Role::create(['name' => $definition['name'], 'slug' => $slug, 'description' => $definition['description'], 'is_system' => true]);
            $role->permissions()->sync(self::expand($definition['permissions'], $definition['except'] ?? [], $all->all()));
            $roles[$slug] = $role;
        }

        return $roles;
    }

    /** Make sure every permission from config/permissions.php exists in the database. */
    public static function syncPermissions(): void
    {
        $existing = Permission::pluck('name')->all();

        foreach (config('permissions.groups') as $group => $actions) {
            foreach ($actions as $action) {
                if (! in_array("{$group}.{$action}", $existing, true)) {
                    Permission::create(['name' => "{$group}.{$action}", 'group' => $group]);
                }
            }
        }
    }

    /** Turn ['*', 'invoices.*', 'x.view'] into permission ids. */
    private static function expand(array $patterns, array $except, array $idsByName): array
    {
        $ids = [];

        foreach ($idsByName as $name => $id) {
            foreach ($patterns as $pattern) {
                if (($pattern === '*' || $pattern === $name || (str_ends_with($pattern, '.*') && str_starts_with($name, substr($pattern, 0, -1)))) && ! in_array($name, $except, true)) {
                    $ids[] = $id;
                    break;
                }
            }
        }

        return $ids;
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'workspace';
        $slug = $base;

        while (Tenant::withTrashed()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.Str::lower(Str::random(4));
        }

        return $slug;
    }

    /** Apply the business type chosen in onboarding: enabled modules + the owner's dashboard widgets. */
    public function applyBusinessType(Tenant $tenant, string $type, ?array $modules = null): void
    {
        $preset = config("saas.business_types.{$type}") ?? config('saas.business_types.other');

        $tenant->update([
            'business_type' => $type,
            'enabled_modules' => array_values(array_intersect($modules ?? $preset['modules'], array_keys(config('saas.modules')))),
        ]);
    }
}
