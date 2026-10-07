<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Invoice;
use App\Services\CustomerService;
use App\Services\ExpenseService;
use App\Services\InvoiceService;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/** Tenant A must never see or touch anything of tenant B. */
class TenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    private function seedBusiness($tenant, $user): array
    {
        return $this->inTenant($tenant, function () use ($user) {
            auth()->setUser($user);
            $customer = app(CustomerService::class)->create(['name' => 'Customer of '.tenant()->name]);
            $invoice = app(InvoiceService::class)->create($this->invoicePayload($customer->id));
            app(InvoiceService::class)->send($invoice, false);
            $payment = app(PaymentService::class)->record($invoice, ['amount' => 50, 'paid_at' => now()]);

            return [$customer, $invoice->refresh(), $payment];
        });
    }

    public function test_queries_without_a_tenant_return_nothing(): void
    {
        [$userA, $tenantA] = $this->workspace();
        $this->seedBusiness($tenantA, $userA);
        $this->freshRequest();

        // Fail closed: no workspace in context means zero rows, never all rows
        $this->assertSame(0, Invoice::count());
        $this->assertSame(0, Customer::count());
        $this->assertSame(1, Invoice::withoutGlobalScope('tenant')->count());
    }

    public function test_lists_only_show_own_records(): void
    {
        [$userA, $tenantA] = $this->workspace();
        [$userB, $tenantB] = $this->workspace();
        [, $invoiceA] = $this->seedBusiness($tenantA, $userA);
        [, $invoiceB] = $this->seedBusiness($tenantB, $userB);
        $this->freshRequest();

        $this->actingAs($userA)->get('/customers')->assertOk()->assertSee('Customer of '.$tenantA->name)->assertDontSee('Customer of '.$tenantB->name);
        $this->freshRequest();
        $this->actingAs($userA)->get('/search?q=Customer', ['Accept' => 'application/json'])->assertOk()
            ->assertJsonMissing(['title' => 'Customer of '.$tenantB->name]);
        $this->freshRequest();

        // Both workspaces start their own number sequence
        $this->assertSame($invoiceA->number, $invoiceB->number);
    }

    public function test_another_tenants_records_cannot_be_viewed_changed_or_deleted(): void
    {
        [$userA, $tenantA] = $this->workspace();
        [$userB, $tenantB] = $this->workspace();
        $this->seedBusiness($tenantA, $userA);
        [$customerB, $invoiceB, $paymentB] = $this->seedBusiness($tenantB, $userB);

        $attempts = [
            ['get', "/invoices/{$invoiceB->id}"], ['get', "/invoices/{$invoiceB->id}/edit"], ['get', "/invoices/{$invoiceB->id}/pdf"],
            ['put', "/invoices/{$invoiceB->id}"], ['delete', "/invoices/{$invoiceB->id}"], ['post', "/invoices/{$invoiceB->id}/cancel"],
            ['post', "/invoices/{$invoiceB->id}/send"], ['post', "/invoices/{$invoiceB->id}/payments"],
            ['get', "/customers/{$customerB->id}"], ['put', "/customers/{$customerB->id}"], ['delete', "/customers/{$customerB->id}"],
            ['get', "/payments/{$paymentB->id}"], ['post', "/payments/{$paymentB->id}/refund"],
        ];

        foreach ($attempts as [$method, $url]) {
            $this->freshRequest();
            $this->actingAs($userA)->{$method}($url, ['amount' => 1, 'paid_at' => now()->toDateString()])->assertNotFound();
        }

        $this->freshRequest();
        $this->assertSame('partially_paid', Invoice::withoutGlobalScope('tenant')->find($invoiceB->id)->status);
        $this->assertNotNull(Customer::withoutGlobalScope('tenant')->find($customerB->id));
    }

    public function test_foreign_ids_in_form_input_are_rejected(): void
    {
        [$userA, $tenantA] = $this->workspace();
        [$userB, $tenantB] = $this->workspace();
        [$customerA] = $this->seedBusiness($tenantA, $userA);
        [$customerB] = $this->seedBusiness($tenantB, $userB);
        $this->freshRequest();

        // Creating an invoice for another workspace's customer
        $this->actingAs($userA)->post('/invoices', $this->invoicePayload($customerB->id))->assertSessionHasErrors('customer_id');

        // tenant_id cannot be mass-assigned through the request
        $this->freshRequest();
        $this->actingAs($userA)->post('/customers', ['name' => 'Injected', 'tenant_id' => $tenantB->id])->assertRedirect();
        $this->freshRequest();
        $this->assertSame($tenantA->id, Customer::withoutGlobalScope('tenant')->where('name', 'Injected')->value('tenant_id'));
    }

    public function test_a_user_cannot_switch_into_a_workspace_they_do_not_belong_to(): void
    {
        [$userA] = $this->workspace();
        [, $tenantB] = $this->workspace();

        $this->actingAs($userA)->post("/workspaces/{$tenantB->id}/switch")->assertForbidden();

        // Even a forged current_tenant_id does not help: membership is checked on every request
        $userA->forceFill(['current_tenant_id' => $tenantB->id])->save();
        $this->freshRequest();
        $this->actingAs($userA)->get('/dashboard')->assertOk();
        $this->assertNotSame($tenantB->id, $userA->fresh()->current_tenant_id);
    }

    public function test_uploaded_files_are_private_to_their_workspace(): void
    {
        Storage::fake('local');
        [$userA, $tenantA] = $this->workspace();
        [$userB] = $this->workspace();

        $receipt = $this->inTenant($tenantA, function () use ($userA) {
            auth()->setUser($userA);
            $expense = app(ExpenseService::class)->create(['amount' => 20, 'currency' => 'USD', 'expense_date' => now()->toDateString()], [UploadedFile::fake()->image('receipt.png')]);

            return $expense->receipts()->first();
        });

        $this->freshRequest();
        $this->actingAs($userA)->get("/expense-receipts/{$receipt->id}")->assertOk();
        $this->freshRequest();
        $this->actingAs($userB)->get("/expense-receipts/{$receipt->id}")->assertNotFound();
        $this->freshRequest();
        $this->get("/expense-receipts/{$receipt->id}")->assertRedirect(route('login'));

        // Stored under a generated name inside the tenant's own folder, never the client file name
        $this->assertStringStartsWith("tenants/{$tenantA->id}/expense_receipts/", $receipt->path);
        $this->assertStringNotContainsString('receipt', basename($receipt->path));
    }

    public function test_public_links_use_unguessable_tokens_and_expose_no_other_tenant(): void
    {
        [$userA, $tenantA] = $this->workspace();
        [, $invoiceA] = $this->seedBusiness($tenantA, $userA);
        $this->freshRequest();

        $this->assertGreaterThanOrEqual(40, strlen($invoiceA->public_token));
        $this->get('/invoice/public/'.$invoiceA->public_token)->assertOk()->assertSee($invoiceA->number);
        $this->freshRequest();
        $this->get('/invoice/public/'.$invoiceA->id)->assertNotFound();
        $this->get('/invoice/public/'.Str::random(48))->assertNotFound();
    }
}
