<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Services\CustomerService;
use App\Services\InvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Roles and permissions are database rows, enforced on the server, not just hidden buttons. */
class PermissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_viewer_can_read_but_not_write(): void
    {
        [$owner, $tenant] = $this->workspace();
        $viewer = $this->member($tenant, 'viewer');
        $customer = $this->inTenant($tenant, fn () => app(CustomerService::class)->create(['name' => 'Acme']));

        $this->actingAs($viewer)->get('/invoices')->assertOk();
        $this->freshRequest()->actingAs($viewer)->get('/customers/'.$customer->id)->assertOk();

        // Direct requests are refused even though the UI shows no button
        $this->freshRequest()->actingAs($viewer)->get('/invoices/create')->assertForbidden();
        $this->freshRequest()->actingAs($viewer)->post('/invoices', $this->invoicePayload($customer->id))->assertForbidden();
        $this->freshRequest()->actingAs($viewer)->delete('/customers/'.$customer->id)->assertForbidden();
        $this->freshRequest()->actingAs($viewer)->get('/settings/billing')->assertForbidden();
        $this->freshRequest()->actingAs($viewer)->post('/team/invite', ['email' => 'x@example.com'])->assertForbidden();
        $this->freshRequest()->actingAs($viewer)->get('/settings/audit-log')->assertForbidden();
    }

    public function test_accountant_handles_money_but_not_team_or_settings(): void
    {
        [, $tenant] = $this->workspace();
        $accountant = $this->member($tenant, 'accountant');
        $customer = $this->inTenant($tenant, fn () => app(CustomerService::class)->create(['name' => 'Acme']));

        $this->actingAs($accountant)->post('/invoices', $this->invoicePayload($customer->id))->assertRedirect();
        $this->freshRequest()->actingAs($accountant)->get('/expenses/create')->assertOk();
        $this->freshRequest()->actingAs($accountant)->get('/team')->assertForbidden();
        $this->freshRequest()->actingAs($accountant)->put('/settings/business', ['name' => 'Hacked', 'timezone' => 'UTC', 'locale' => 'en'])->assertForbidden();
        $this->freshRequest()->actingAs($accountant)->post('/customers', ['name' => 'New'])->assertForbidden();
    }

    public function test_changing_a_roles_permissions_takes_effect(): void
    {
        [$owner, $tenant] = $this->workspace();
        $viewer = $this->member($tenant, 'viewer');
        $role = Role::withoutGlobalScope('tenant')->where('tenant_id', $tenant->id)->where('slug', 'viewer')->first();

        $this->actingAs($viewer)->get('/customers/create')->assertForbidden();

        $ids = Permission::whereIn('name', ['customers.view', 'customers.create', 'dashboard.view'])->pluck('id')->all();
        $this->freshRequest()->actingAs($owner)->put('/roles/'.$role->id, ['name' => 'Viewer', 'permissions' => $ids])->assertRedirect();

        $this->freshRequest()->actingAs($viewer)->get('/customers/create')->assertOk();
        $this->freshRequest()->actingAs($viewer)->get('/invoices')->assertForbidden();
    }

    public function test_custom_roles_can_be_created_and_owner_role_is_protected(): void
    {
        [$owner, $tenant] = $this->workspace();
        $ownerRole = Role::withoutGlobalScope('tenant')->where('tenant_id', $tenant->id)->where('slug', 'owner')->first();

        $this->actingAs($owner)->post('/roles', ['name' => 'Accounting Manager', 'permissions' => Permission::where('group', 'invoices')->pluck('id')->all()])->assertRedirect();
        $this->assertDatabaseHas('roles', ['tenant_id' => $tenant->id, 'slug' => 'accounting_manager', 'is_system' => false]);

        $this->freshRequest()->actingAs($owner)->get('/roles/'.$ownerRole->id.'/edit')->assertForbidden();
        $this->freshRequest()->actingAs($owner)->delete('/roles/'.$ownerRole->id)->assertSessionHas('error');
    }

    public function test_owner_cannot_be_removed_and_suspended_members_lose_access(): void
    {
        [$owner, $tenant] = $this->workspace();
        $admin = $this->member($tenant, 'administrator');
        $staff = $this->member($tenant, 'sales_staff');

        $this->actingAs($admin)->delete('/team/'.$owner->id)->assertForbidden();
        $this->freshRequest()->actingAs($admin)->post('/team/'.$staff->id.'/toggle')->assertRedirect();
        $this->assertSame('suspended', DB::table('tenant_user')->where('user_id', $staff->id)->value('status'));

        // The suspended member has no active workspace left
        $this->freshRequest()->actingAs($staff)->get('/dashboard')->assertRedirect(route('workspaces.create'));
    }

    public function test_menu_only_shows_what_the_role_may_open(): void
    {
        [$owner, $tenant] = $this->workspace();
        $inventory = $this->member($tenant, 'inventory_manager');

        $this->actingAs($inventory)->get('/dashboard')->assertOk()->assertSee(route('inventory.index'))->assertDontSee(route('invoices.index').'"', false)->assertDontSee(route('team.index'));
        $this->freshRequest()->actingAs($owner)->get('/dashboard')->assertSee(route('invoices.index'))->assertSee(route('team.index'));
    }

    public function test_platform_admin_area_is_hidden_from_ordinary_users(): void
    {
        [$owner] = $this->workspace();

        $this->actingAs($owner)->get('/admin')->assertNotFound();
        $this->freshRequest()->get('/admin')->assertRedirect(route('login'));
    }

    public function test_sensitive_changes_are_written_to_the_audit_log(): void
    {
        [$owner, $tenant] = $this->workspace();
        $customer = $this->inTenant($tenant, fn () => app(CustomerService::class)->create(['name' => 'Acme']));

        $this->actingAs($owner)->post('/invoices', $this->invoicePayload($customer->id));
        $invoice = $this->inTenant($tenant, fn () => \App\Models\Invoice::first());
        $this->freshRequest()->actingAs($owner)->put('/invoices/'.$invoice->id, $this->invoicePayload($customer->id, ['items' => [['name' => 'Design work', 'quantity' => 2, 'unit_price' => 325]]]));

        // One edit can write several entries (totals, then status); find the one that changed the total
        $log = DB::table('audit_logs')->where('entity_type', 'Invoice')->where('action', 'updated')->orderByDesc('id')->get()
            ->first(fn ($row) => array_key_exists('total', json_decode($row->new_values, true)));
        $this->assertNotNull($log);
        $this->assertSame($owner->id, $log->user_id);
        $this->assertSame($tenant->id, $log->tenant_id);
        $this->assertEquals(200, (float) json_decode($log->old_values, true)['total']);
        $this->assertEquals(650, (float) json_decode($log->new_values, true)['total']);
    }
}
