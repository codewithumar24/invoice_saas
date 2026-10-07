<?php

namespace App\Services\Gateways;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentMethod;
use Illuminate\Http\Request;

/**
 * Adapter for local gateways that follow a simple pattern:
 * redirect the customer to a checkout URL, receive an HMAC-signed JSON webhook.
 * Method config: checkout_url (may contain {token} {amount} {currency} {number}), webhook_secret.
 * Expected webhook body: {"id","status":"paid|failed","invoice_token","transaction_id","amount"}
 * Expected header: X-Signature = hex HMAC-SHA256 of the raw body.
 */
class CustomGateway implements PaymentGatewayInterface
{
    public function key(): string
    {
        return 'custom';
    }

    public function supportsCheckout(): bool
    {
        return true;
    }

    public function checkoutUrl(Invoice $invoice, PaymentMethod $method): string
    {
        return strtr((string) ($method->config['checkout_url'] ?? ''), [
            '{token}' => $invoice->public_token,
            '{amount}' => (string) $invoice->balance_due,
            '{currency}' => $invoice->currency,
            '{number}' => rawurlencode($invoice->number),
        ]);
    }

    public function verifyWebhook(Request $request, PaymentMethod $method): bool
    {
        $secret = (string) ($method->config['webhook_secret'] ?? '');

        return $secret !== '' && hash_equals(hash_hmac('sha256', $request->getContent(), $secret), (string) $request->header('X-Signature'));
    }

    public function parseWebhook(Request $request): ?array
    {
        $data = $request->json()->all();

        if (empty($data['id']) || empty($data['invoice_token'])) {
            return null;
        }

        return [
            'event_id' => (string) $data['id'],
            'type' => ($data['status'] ?? '') === 'paid' ? 'payment_succeeded' : 'payment_failed',
            'invoice_token' => $data['invoice_token'],
            'transaction_id' => (string) ($data['transaction_id'] ?? $data['id']),
            'amount' => (string) ($data['amount'] ?? '0'),
        ];
    }

    public function refund(Payment $payment, string $amount, PaymentMethod $method): bool
    {
        return true; // refund is done in the gateway dashboard; we record it here
    }
}
