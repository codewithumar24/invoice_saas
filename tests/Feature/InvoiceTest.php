<?php

namespace Tests\Feature;

use App\Jobs\SendInvoiceEmailJob;
use App\Mail\TemplateMail;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\TaxRate;
use App\Services\CustomerService;
use App\Services\InventoryService;
use App\Services\InvoiceService;
use App\Services\NumberingService;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class InvoiceTest extends TestCase
{
    use RefreshDatabase;

    private function setUpBusiness(): array
    {
        [$user, $tenant] = $this->workspace();
        $customer = $this->inTenant($tenant, fn () => app(CustomerService::class)->create(['name' => 'Acme', 'email' => 'billing@acme.test']));

        return [$user, $tenant, $customer];
    }

    public function test_totals_are_calculated_on_the_server_and_client_totals_are_ignored(): void
    {
        [$user, $tenant, $customer] = $this->setUpBusiness();
        $tax = $this->inTenant($tenant, fn () => TaxRate::create(['name' => 'VAT', 'rate' => 20]));

        $this->actingAs($user)->post('/invoices', $this->invoicePayload($customer->id, [
            'discount_value' => 10, 'shipping' => 5,
            'items' => [['name' => 'Design', 'quantity' => 2, 'unit_price' => 100, 'tax_rate_id' => $tax->id, 'line_total' => 1, 'tax_amount' => 0]],
            // A tampered browser could send these; they must not be used
            'subtotal' => 1, 'total' => 1, 'tax_total' => 0, 'balance_due' => 0, 'status' => 'paid', 'amount_paid' => 999,
        ]))->assertRedirect();

        $invoice = $this->inTenant($tenant, fn () => Invoice::with('items', 'taxes')->first());

        $this->assertEquals(200, (float) $invoice->subtotal);
        $this->assertEquals(20, (float) $invoice->discount_total);
        $this->assertEquals(36, (float) $invoice->tax_total);       // 20% of (200 - 20)
        $this->assertEquals(221, (float) $invoice->total);          // 180 + 36 + 5
        $this->assertEquals(221, (float) $invoice->balance_due);
        $this->assertEquals(0, (float) $invoice->amount_paid);
        $this->assertSame('draft', $invoice->status);
        $this->assertSame('VAT', $invoice->taxes->first()->name);
        $this->assertEquals(20, (float) $invoice->items->first()->tax_rate);
    }

    public function test_validation_errors_keep_the_user_on_the_form(): void
    {
        [$user, , $customer] = $this->setUpBusiness();

        $this->actingAs($user)->post('/invoices', $this->invoicePayload($customer->id, ['items' => []]))->assertSessionHasErrors('items');
        $this->freshRequest()->actingAs($user)->post('/invoices', $this->invoicePayload($customer->id, ['due_date' => now()->subYear()->toDateString()]))->assertSessionHasErrors('due_date');
        $this->freshRequest()->actingAs($user)->post('/invoices', $this->invoicePayload($customer->id, ['items' => [['name' => '', 'quantity' => -1, 'unit_price' => 'abc']]]))
            ->assertSessionHasErrors(['items.0.name', 'items.0.quantity', 'items.0.unit_price']);
    }

    public function test_invoice_numbers_are_sequential_per_workspace_and_never_repeat(): void
    {
        [, $tenant, $customer] = $this->setUpBusiness();

        $numbers = $this->inTenant($tenant, function () use ($customer) {
            return collect(range(1, 5))->map(fn () => app(InvoiceService::class)->create($this->invoicePayload($customer->id))->number);
        });

        $year = now()->format('Y');
        $this->assertSame(["INV-{$year}-00001", "INV-{$year}-00002", "INV-{$year}-00003", "INV-{$year}-00004", "INV-{$year}-00005"], $numbers->all());
        $this->assertSame(5, $numbers->unique()->count());
    }

    public function test_numbering_settings_prefix_start_and_reset(): void
    {
        [, $tenant] = $this->setUpBusiness();

        $this->inTenant($tenant, function () {
            app(SettingsService::class)->set(['invoice_prefix' => 'FA', 'invoice_next_number' => 500, 'invoice_reset' => 'never', 'invoice_padding' => 4]);
            $this->assertSame('FA-0500', app(NumberingService::class)->next('invoice'));
            $this->assertSame('FA-0501', app(NumberingService::class)->next('invoice'));

            app(SettingsService::class)->set(['invoice_reset' => 'monthly']);
            $this->assertSame('FA-'.now()->format('Ym').'-0500', app(NumberingService::class)->next('invoice'));
        });
    }

    public function test_sending_queues_the_email_job_with_the_pdf(): void
    {
        [$user, $tenant, $customer] = $this->setUpBusiness();
        $invoice = $this->inTenant($tenant, fn () => app(InvoiceService::class)->create($this->invoicePayload($customer->id)));

        Queue::fake();
        $this->actingAs($user)->post("/invoices/{$invoice->id}/send")->assertSessionHas('success');
        Queue::assertPushedOn('emails', SendInvoiceEmailJob::class);
        $this->assertSame('sent', $invoice->fresh()->status);

        // Running the job renders the PDF and queues the mail to the customer
        Mail::fake();
        (new SendInvoiceEmailJob($tenant->id, $invoice->id))->handle(app(\App\Support\TenantContext::class));
        Mail::assertQueued(TemplateMail::class, fn ($mail) => $mail->hasTo('billing@acme.test') && count($mail->fileList) === 1 && str_contains($mail->mailSubject, $invoice->number));
    }

    public function test_pdf_download_and_public_view_tracking(): void
    {
        [$user, $tenant, $customer] = $this->setUpBusiness();
        $invoice = $this->inTenant($tenant, function () use ($customer) {
            $invoice = app(InvoiceService::class)->create($this->invoicePayload($customer->id));

            return app(InvoiceService::class)->send($invoice, false);
        });

        $pdf = $this->actingAs($user)->get("/invoices/{$invoice->id}/pdf")->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $pdf->getContent());

        // A draft has no public page; a sent invoice is marked "viewed" when the customer opens it
        $this->freshRequest();
        $this->get('/invoice/public/'.$invoice->public_token)->assertOk()->assertSee($invoice->number)->assertDontSee('Internal');
        $this->freshRequest();
        $this->assertSame('viewed', Invoice::withoutGlobalScope('tenant')->find($invoice->id)->status);
        $this->assertDatabaseCount('invoice_views', 1);
    }

    public function test_drafts_are_not_public(): void
    {
        [, $tenant, $customer] = $this->setUpBusiness();
        $invoice = $this->inTenant($tenant, fn () => app(InvoiceService::class)->create($this->invoicePayload($customer->id)));
        $this->freshRequest();

        $this->get('/invoice/public/'.$invoice->public_token)->assertNotFound();
    }

    public function test_only_drafts_are_deleted_and_issued_invoices_are_cancelled(): void
    {
        [$user, $tenant, $customer] = $this->setUpBusiness();
        [$draft, $sent] = $this->inTenant($tenant, function () use ($customer) {
            $draft = app(InvoiceService::class)->create($this->invoicePayload($customer->id));
            $sent = app(InvoiceService::class)->send(app(InvoiceService::class)->create($this->invoicePayload($customer->id)), false);

            return [$draft, $sent];
        });

        $this->actingAs($user)->delete("/invoices/{$sent->id}")->assertSessionHasErrors('invoice');
        $this->freshRequest()->actingAs($user)->delete("/invoices/{$draft->id}")->assertRedirect(route('invoices.index'));
        $this->freshRequest()->actingAs($user)->post("/invoices/{$sent->id}/cancel")->assertSessionHas('success');

        $this->assertSoftDeleted('invoices', ['id' => $draft->id]);
        $this->assertDatabaseHas('invoices', ['id' => $sent->id, 'status' => 'cancelled', 'deleted_at' => null]);

        // A cancelled invoice can no longer be edited
        $this->freshRequest()->actingAs($user)->put("/invoices/{$sent->id}", $this->invoicePayload($customer->id))->assertSessionHasErrors('invoice');
    }

    public function test_overdue_detection(): void
    {
        [, $tenant, $customer] = $this->setUpBusiness();

        $this->inTenant($tenant, function () use ($customer) {
            $service = app(InvoiceService::class);
            $late = $service->send($service->create($this->invoicePayload($customer->id, ['issue_date' => now()->subDays(40)->toDateString(), 'due_date' => now()->subDays(10)->toDateString()])), false);
            $onTime = $service->send($service->create($this->invoicePayload($customer->id)), false);

            $this->assertTrue($late->fresh()->isOverdue());
            $this->assertSame(10, $late->fresh()->daysOverdue());
            $this->assertFalse($onTime->fresh()->isOverdue());
            $this->assertSame(1, Invoice::overdue()->count());

            $late->forceFill(['status' => 'sent'])->saveQuietly();
            $this->assertSame(1, $service->markOverdue());
            $this->assertSame('overdue', $late->fresh()->status);
            $this->assertSame(0, $service->markOverdue(), 'already flagged invoices are not flagged twice');
        });
    }

    public function test_inventory_moves_with_the_invoice(): void
    {
        [, $tenant, $customer] = $this->setUpBusiness();

        $this->inTenant($tenant, function () use ($customer) {
            $inventory = app(InventoryService::class);
            $product = Product::create(['type' => 'product', 'name' => 'Notebook', 'selling_price' => 10, 'purchase_price' => 4, 'track_inventory' => true, 'minimum_stock' => 5]);
            $inventory->move($product, $inventory->defaultLocation()->id, 'purchase', '20');

            $service = app(InvoiceService::class);
            $invoice = $service->create($this->invoicePayload($customer->id, ['items' => [['product_id' => $product->id, 'name' => 'Notebook', 'quantity' => 8, 'unit_price' => 10]]]));
            $this->assertEquals(20, (float) $product->fresh()->stock_quantity, 'a draft does not touch stock');

            $service->send($invoice, false);
            $this->assertEquals(12, (float) $product->fresh()->stock_quantity);

            // Editing the quantity only moves the difference
            $service->update($invoice->fresh(), $this->invoicePayload($customer->id, ['items' => [['product_id' => $product->id, 'name' => 'Notebook', 'quantity' => 16, 'unit_price' => 10]]]));
            $this->assertEquals(4, (float) $product->fresh()->stock_quantity);
            $this->assertTrue($product->fresh()->isLowStock());

            $service->cancel($invoice->fresh());
            $this->assertEquals(20, (float) $product->fresh()->stock_quantity, 'cancelling puts the stock back');
        });
    }

    public function test_duplicate_and_multi_currency_rate_is_frozen(): void
    {
        [, $tenant, $customer] = $this->setUpBusiness();

        $this->inTenant($tenant, function () use ($customer) {
            \App\Models\ExchangeRate::create(['currency' => 'EUR', 'rate' => 1.10, 'effective_date' => now()->subDay()->toDateString()]);
            $service = app(InvoiceService::class);
            $invoice = $service->create($this->invoicePayload($customer->id, ['currency' => 'EUR']));
            $this->assertEquals(1.10, (float) $invoice->exchange_rate);

            // A newer rate does not change the existing invoice
            \App\Models\ExchangeRate::create(['currency' => 'EUR', 'rate' => 1.50, 'effective_date' => now()->toDateString()]);
            $service->update($invoice, $this->invoicePayload($customer->id, ['currency' => 'EUR']));
            $this->assertEquals(1.10, (float) $invoice->fresh()->exchange_rate);

            $copy = $service->duplicate($invoice->fresh()->load('items'));
            $this->assertNotSame($invoice->number, $copy->number);
            $this->assertSame('draft', $copy->status);
            $this->assertEquals((float) $invoice->fresh()->total, (float) $copy->total);
        });
    }

    public function test_plan_limits_are_enforced(): void
    {
        [$user, $tenant] = $this->workspace('free'); // free plan: 5 customers

        $this->inTenant($tenant, function () {
            foreach (range(1, 5) as $i) {
                app(CustomerService::class)->create(['name' => "Customer {$i}"]);
            }
        });

        $this->actingAs($user)->post('/customers', ['name' => 'One too many'])->assertSessionHasErrors('limit');
        $this->assertSame(5, Customer::withoutGlobalScope('tenant')->count());

        // Modules outside the plan are closed on the server as well
        $this->freshRequest()->actingAs($user)->get('/inventory')->assertRedirect(route('dashboard'));
        $this->freshRequest()->actingAs($user)->get('/settings/api-tokens')->assertRedirect(route('dashboard'));
    }
}
