<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\RecurringInvoice;
use App\Services\CustomerService;
use App\Services\InvoiceService;
use App\Services\PaymentService;
use App\Services\RecurringInvoiceService;
use App\Services\ReminderService;
use App\Jobs\SendPaymentReminderJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class PaymentTest extends TestCase
{
    use RefreshDatabase;

    private function sentInvoice(): array
    {
        [$user, $tenant] = $this->workspace();
        [$customer, $invoice] = $this->inTenant($tenant, function () {
            $customer = app(CustomerService::class)->create(['name' => 'Acme', 'email' => 'billing@acme.test']);
            $invoice = app(InvoiceService::class)->send(app(InvoiceService::class)->create($this->invoicePayload($customer->id)), false);

            return [$customer, $invoice];
        });

        return [$user, $tenant, $customer, $invoice];
    }

    public function test_partial_then_full_payment_updates_invoice_and_customer_balance(): void
    {
        [$user, , $customer, $invoice] = $this->sentInvoice();

        $this->actingAs($user)->post("/invoices/{$invoice->id}/payments", ['amount' => 80, 'paid_at' => now()->toDateString()])->assertSessionHas('success');
        $this->assertDatabaseHas('invoices', ['id' => $invoice->id, 'status' => 'partially_paid', 'amount_paid' => 80, 'balance_due' => 120]);
        $this->assertEquals(120, (float) $customer->fresh()->balance);

        $this->freshRequest()->actingAs($user)->post("/invoices/{$invoice->id}/payments", ['amount' => 120, 'paid_at' => now()->toDateString()]);
        $this->assertDatabaseHas('invoices', ['id' => $invoice->id, 'status' => 'paid', 'balance_due' => 0]);
        $this->assertEquals(0, (float) $customer->fresh()->balance);
        $this->assertSame(2, DB::table('payment_transactions')->where('type', 'payment')->count());
    }

    public function test_overpayment_and_invalid_amounts_are_rejected(): void
    {
        [$user, , , $invoice] = $this->sentInvoice();

        $this->actingAs($user)->post("/invoices/{$invoice->id}/payments", ['amount' => 500, 'paid_at' => now()->toDateString()])->assertSessionHasErrors('amount');
        $this->freshRequest()->actingAs($user)->post("/invoices/{$invoice->id}/payments", ['amount' => 0, 'paid_at' => now()->toDateString()])->assertSessionHasErrors('amount');
        $this->freshRequest()->actingAs($user)->post("/invoices/{$invoice->id}/payments", ['amount' => -5, 'paid_at' => now()->toDateString()])->assertSessionHasErrors('amount');
        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseHas('invoices', ['id' => $invoice->id, 'status' => 'sent', 'amount_paid' => 0]);
    }

    public function test_refund_keeps_the_payment_and_reopens_the_invoice(): void
    {
        [$user, $tenant, $customer, $invoice] = $this->sentInvoice();
        $payment = $this->inTenant($tenant, fn () => app(PaymentService::class)->record($invoice, ['amount' => 200, 'paid_at' => now()]));
        $this->assertSame('paid', $invoice->fresh()->status);

        $this->actingAs($user)->post("/payments/{$payment->id}/refund", ['amount' => 50, 'reason' => 'Goodwill'])->assertSessionHas('success');

        $this->assertDatabaseHas('payments', ['id' => $payment->id, 'status' => 'partially_refunded', 'refunded_amount' => 50, 'amount' => 200]);
        $this->assertDatabaseHas('invoices', ['id' => $invoice->id, 'status' => 'partially_paid', 'balance_due' => 50]);
        $this->assertDatabaseHas('payment_transactions', ['payment_id' => $payment->id, 'type' => 'refund', 'amount' => 50]);
        $this->assertEquals(50, (float) $customer->fresh()->balance);

        // Cannot refund more than what is left
        $this->freshRequest()->actingAs($user)->post("/payments/{$payment->id}/refund", ['amount' => 151])->assertSessionHasErrors('amount');

        $this->freshRequest()->actingAs($user)->post("/payments/{$payment->id}/refund", ['amount' => 150]);
        $this->assertDatabaseHas('payments', ['id' => $payment->id, 'status' => 'refunded']);
        $this->assertDatabaseHas('invoices', ['id' => $invoice->id, 'status' => 'sent', 'amount_paid' => 0]);
        $this->assertSame(1, Payment::withoutGlobalScope('tenant')->count(), 'the payment record is never deleted');
    }

    public function test_a_failed_payment_rolls_everything_back(): void
    {
        [, $tenant, , $invoice] = $this->sentInvoice();

        $this->inTenant($tenant, function () use ($invoice) {
            // Force a failure in the middle of the transaction: the ledger table refuses a null currency
            DB::statement('CREATE TRIGGER fail_ledger BEFORE INSERT ON payment_transactions BEGIN SELECT RAISE(ABORT, "boom"); END');

            try {
                app(PaymentService::class)->record($invoice, ['amount' => 100, 'paid_at' => now()]);
                $this->fail('The payment should have failed.');
            } catch (\Illuminate\Database\QueryException) {
                // expected
            }

            DB::statement('DROP TRIGGER fail_ledger');
        });

        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseCount('invoice_payments', 0);
        $this->assertDatabaseHas('invoices', ['id' => $invoice->id, 'status' => 'sent', 'amount_paid' => 0, 'balance_due' => 200]);
    }

    /* ------------------------------ Gateway webhooks ------------------------------ */

    private function stripeEvent(Invoice $invoice, string $eventId, int $cents): string
    {
        return json_encode(['id' => $eventId, 'type' => 'checkout.session.completed', 'data' => ['object' => [
            'id' => 'cs_1', 'payment_status' => 'paid', 'currency' => 'usd', 'amount_total' => $cents, 'payment_intent' => 'pi_'.$eventId,
            'metadata' => ['invoice_token' => $invoice->public_token],
        ]]]);
    }

    private function postWebhook(string $url, string $body, string $secret)
    {
        $timestamp = time();

        return $this->call('POST', $url, [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_STRIPE_SIGNATURE' => 't='.$timestamp.',v1='.hash_hmac('sha256', $timestamp.'.'.$body, $secret),
        ], $body);
    }

    public function test_gateway_webhook_must_be_signed_and_is_idempotent(): void
    {
        [, $tenant, , $invoice] = $this->sentInvoice();
        $this->inTenant($tenant, fn () => PaymentMethod::create(['name' => 'Card', 'type' => 'stripe', 'config' => ['secret_key' => 'sk_test', 'webhook_secret' => 'whsec_abc']]));
        $url = "/webhooks/payments/stripe/{$tenant->slug}";
        $body = $this->stripeEvent($invoice, 'evt_100', 20000);
        $this->freshRequest();

        // 1. Wrong signature: rejected, nothing recorded
        $this->postWebhook($url, $body, 'wrong_secret')->assertStatus(400);
        $this->assertDatabaseCount('payments', 0);

        // 2. Valid signature: payment recorded, invoice paid, no CSRF token needed
        $this->freshRequest();
        $this->postWebhook($url, $body, 'whsec_abc')->assertOk();
        $this->assertDatabaseHas('payments', ['gateway' => 'stripe', 'transaction_id' => 'pi_evt_100', 'amount' => 200, 'status' => 'paid']);
        $this->assertDatabaseHas('invoices', ['id' => $invoice->id, 'status' => 'paid']);

        // 3. The same event delivered three more times: still exactly one payment
        foreach (range(1, 3) as $i) {
            $this->freshRequest();
            $this->postWebhook($url, $body, 'whsec_abc')->assertOk()->assertJson(['message' => 'Already processed.']);
        }
        $this->assertDatabaseCount('payments', 1);
        $this->assertDatabaseCount('webhook_events', 1);
    }

    public function test_the_browser_cannot_mark_an_invoice_paid(): void
    {
        [, $tenant, , $invoice] = $this->sentInvoice();
        $this->freshRequest();

        // The "?paid=1" return URL only shows a message; status comes from the webhook alone
        $this->get('/invoice/public/'.$invoice->public_token.'?paid=1')->assertOk();
        $this->assertSame(0.0, (float) Invoice::withoutGlobalScope('tenant')->find($invoice->id)->amount_paid);
    }

    /* ------------------------------ Recurring invoices and reminders ------------------------------ */

    public function test_recurring_invoices_generate_once_and_move_to_the_next_date(): void
    {
        [$user, $tenant, $customer] = $this->sentInvoice();
        Queue::fake();

        $this->inTenant($tenant, function () use ($customer) {
            $rule = RecurringInvoice::create([
                'customer_id' => $customer->id, 'frequency' => 'monthly', 'start_date' => now()->toDateString(), 'next_run_date' => now()->toDateString(),
                'due_days' => 7, 'currency' => 'USD', 'auto_send' => true, 'items' => [['name' => 'Retainer', 'quantity' => 1, 'unit_price' => 500]],
            ]);

            $service = app(RecurringInvoiceService::class);
            $this->assertSame(1, $service->processDue());
            $this->assertSame(0, $service->processDue(), 'running the scheduler twice does not create a second invoice');

            $rule->refresh();
            $this->assertSame(1, $rule->invoices_generated);
            $this->assertTrue($rule->next_run_date->isSameDay(now()->addMonthNoOverflow()));

            $invoice = Invoice::where('recurring_invoice_id', $rule->id)->firstOrFail();
            $this->assertEquals(500, (float) $invoice->total);
            $this->assertSame('sent', $invoice->status);
            $this->assertTrue($invoice->due_date->isSameDay(now()->addDays(7)));
        });

        Queue::assertPushed(\App\Jobs\SendInvoiceEmailJob::class);
    }

    public function test_reminders_are_sent_once_per_day_and_only_for_unpaid_invoices(): void
    {
        [, $tenant, $customer] = $this->sentInvoice();
        Queue::fake();

        $this->inTenant($tenant, function () use ($customer) {
            $service = app(InvoiceService::class);
            $dueSoon = $service->send($service->create($this->invoicePayload($customer->id, ['due_date' => now()->addDays(3)->toDateString()])), false);
            $overdue = $service->send($service->create($this->invoicePayload($customer->id, ['issue_date' => now()->subDays(20)->toDateString(), 'due_date' => now()->subDays(7)->toDateString()])), false);
            $paid = $service->send($service->create($this->invoicePayload($customer->id, ['due_date' => now()->addDays(3)->toDateString()])), false);
            app(PaymentService::class)->record($paid, ['amount' => 200, 'paid_at' => now()]);

            $this->assertSame(2, app(ReminderService::class)->run());
            $this->assertSame(0, app(ReminderService::class)->run(), 'no second reminder on the same day');
        });

        Queue::assertPushed(SendPaymentReminderJob::class, 2);
        Queue::assertPushed(SendPaymentReminderJob::class, fn ($job) => $job->type === 'before_due' && $job->days === 3);
        Queue::assertPushed(SendPaymentReminderJob::class, fn ($job) => $job->type === 'overdue' && $job->days === 7);
    }
}
