<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\PaymentMethod;
use App\Models\Tenant;
use App\Models\WebhookEvent;
use App\Services\Gateways\GatewayManager;
use App\Services\Gateways\StripeGateway;
use App\Services\NotificationService;
use App\Services\PaymentService;
use App\Services\SubscriptionService;
use App\Support\TenantContext;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Incoming gateway webhooks: verify signature -> record event (idempotent) -> process -> log.
 * Payment status is only ever changed here, never from a browser redirect.
 */
class WebhookController extends Controller
{
    /** Customer invoice payments for one workspace. */
    public function payments(Request $request, string $provider, Tenant $tenant, GatewayManager $gateways, PaymentService $payments, TenantContext $context)
    {
        abort_unless($tenant->isActive(), 404);
        $context->set($tenant);

        $method = PaymentMethod::where('type', $provider)->where('is_active', true)->first();
        $gateway = $gateways->driver($provider);

        if (! $method || ! $gateway->verifyWebhook($request, $method)) {
            Log::warning('Payment webhook rejected: invalid signature.', ['provider' => $provider, 'tenant' => $tenant->id, 'ip' => $request->ip()]);

            return response()->json(['success' => false, 'message' => 'Invalid signature.'], 400);
        }

        $event = $gateway->parseWebhook($request);

        if (! $event) {
            return response()->json(['success' => true, 'message' => 'Ignored.']);
        }

        // Idempotency: the same provider event is stored once. A repeat delivery stops here.
        if (! $this->claim($provider.':'.$tenant->id, $event['event_id'], $event['type'])) {
            return response()->json(['success' => true, 'message' => 'Already processed.']);
        }

        $invoice = Invoice::where('public_token', $event['invoice_token'])->first();

        if ($invoice && $event['type'] === 'payment_succeeded') {
            $payments->recordFromGateway($invoice, $provider, (string) $event['transaction_id'], (string) $event['amount'], $method->id);
        } elseif ($invoice && $event['type'] === 'payment_failed') {
            app(NotificationService::class)->team('payments', __('Online payment failed for invoice :number', ['number' => $invoice->number]), $invoice->customer?->name, route('invoices.show', $invoice), 'payments.view');

            if ($invoice->customer?->email) {
                app(NotificationService::class)->email($invoice->customer->email, 'payment_failed', app(\App\Services\EmailTemplateService::class)->invoiceVariables($invoice), [], $invoice->publicUrl(), __('Try again'));
            }
        }

        WebhookEvent::where('provider', $provider.':'.$tenant->id)->where('event_id', $event['event_id'])->update(['processed_at' => now()]);
        Log::info('Payment webhook processed.', ['provider' => $provider, 'tenant' => $tenant->id, 'type' => $event['type']]);

        return response()->json(['success' => true, 'message' => 'Processed.']);
    }

    /** Platform subscription billing (Stripe). */
    public function billing(Request $request, SubscriptionService $subscriptions)
    {
        if (! StripeGateway::validSignature($request->getContent(), (string) $request->header('Stripe-Signature'), (string) config('services.stripe.webhook_secret'))) {
            Log::warning('Billing webhook rejected: invalid signature.', ['ip' => $request->ip()]);

            return response()->json(['success' => false, 'message' => 'Invalid signature.'], 400);
        }

        $event = $request->json()->all();

        if (empty($event['id']) || ! $this->claim('stripe-billing', $event['id'], $event['type'] ?? null)) {
            return response()->json(['success' => true, 'message' => 'Already processed.']);
        }

        $subscriptions->handleGatewayEvent($event);
        WebhookEvent::where('provider', 'stripe-billing')->where('event_id', $event['id'])->update(['processed_at' => now()]);

        return response()->json(['success' => true, 'message' => 'Processed.']);
    }

    /** The unique index on (provider, event_id) makes this safe even when two deliveries arrive at the same moment. */
    private function claim(string $provider, string $eventId, ?string $type): bool
    {
        try {
            WebhookEvent::create(['provider' => $provider, 'event_id' => $eventId, 'type' => $type]);

            return true;
        } catch (UniqueConstraintViolationException) {
            return false;
        }
    }
}
