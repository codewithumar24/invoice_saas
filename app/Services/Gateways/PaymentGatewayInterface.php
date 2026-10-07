<?php

namespace App\Services\Gateways;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentMethod;
use Illuminate\Http\Request;

/**
 * Contract every payment provider implements. Invoice logic only talks to this interface,
 * so a new gateway is one new class plus one line in GatewayManager.
 */
interface PaymentGatewayInterface
{
    public function key(): string;

    /** Can the customer pay online with this gateway? */
    public function supportsCheckout(): bool;

    /** URL the customer is redirected to in order to pay the invoice. */
    public function checkoutUrl(Invoice $invoice, PaymentMethod $method): string;

    /** Verify that the webhook really comes from the provider. Never trust an unverified webhook. */
    public function verifyWebhook(Request $request, PaymentMethod $method): bool;

    /**
     * Translate a provider webhook into a neutral array, or null when the event is not relevant:
     * ['event_id', 'type' => payment_succeeded|payment_failed, 'invoice_token', 'transaction_id', 'amount']
     */
    public function parseWebhook(Request $request): ?array;

    public function refund(Payment $payment, string $amount, PaymentMethod $method): bool;
}
