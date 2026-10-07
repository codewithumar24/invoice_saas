<?php

namespace Tests;

use App\Models\Plan;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Services\SubscriptionService;
use App\Services\TenantService;
use App\Support\TenantContext;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;

abstract class TestCase extends BaseTestCase
{
    /**
     * Create a verified user who owns an onboarded workspace on the given plan.
     *
     * @return array{0: User, 1: Tenant}
     */
    protected function workspace(string $plan = 'business', array $tenantData = []): array
    {
        if (! Plan::exists()) {
            $this->seed(DatabaseSeeder::class);
        }

        $user = User::factory()->create();
        $tenant = app(TenantService::class)->create($user, $tenantData + ['name' => fake()->company(), 'timezone' => 'UTC', 'currency' => 'USD']);
        $tenant->update(['onboarded_at' => now(), 'enabled_modules' => array_keys(config('saas.modules'))]);
        app(SubscriptionService::class)->changePlan($tenant, Plan::where('slug', $plan)->firstOrFail());

        return [$user->refresh(), $tenant->refresh()];
    }

    /** Add a member with one of the default roles (accountant, viewer, sales_staff ...). */
    protected function member(Tenant $tenant, string $roleSlug): User
    {
        $user = User::factory()->create();
        $tenant->users()->attach($user->id, ['is_owner' => false, 'status' => 'active']);
        $role = Role::withoutGlobalScope('tenant')->where('tenant_id', $tenant->id)->where('slug', $roleSlug)->firstOrFail();
        DB::table('user_roles')->insert(['user_id' => $user->id, 'role_id' => $role->id, 'tenant_id' => $tenant->id]);
        $user->forceFill(['current_tenant_id' => $tenant->id])->save();

        return $user;
    }

    /** Run code inside a workspace, the way a request would. */
    protected function inTenant(Tenant $tenant, callable $callback): mixed
    {
        return app(TenantContext::class)->run($tenant, $callback);
    }

    /** Forget the request state between two simulated requests. */
    protected function freshRequest(): static
    {
        app(TenantContext::class)->set(null);
        $this->flushSession();
        auth()->forgetGuards();

        return $this;
    }

    protected function invoicePayload(int $customerId, array $overrides = []): array
    {
        return $overrides + [
            'customer_id' => $customerId,
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(14)->toDateString(),
            'currency' => 'USD',
            'discount_type' => 'percent',
            'items' => [['name' => 'Design work', 'quantity' => 2, 'unit_price' => 100]],
        ];
    }
}
