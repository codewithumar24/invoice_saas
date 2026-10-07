<?php

namespace App\Services\Gateways;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * PayPal Orders API v2.
 * Method config: client_id, client_secret, webhook_id, mode (sandbox|live).
 */
class PayPalGateway implements PaymentGatewayInterface
{
    public function key(): string
    {
        return 'paypal';
    }

    public function supportsCheckout(): bool
    {
        return true;
    }

    private function base(PaymentMethod $method): string
    {
        return ($method->config['mode'] ?? 'sandbox') === 'live' ? 'https://api-m.paypal.com' : 'https://api-m.sandbox.paypal.com';
    }

    private function token(PaymentMethod $method): string
    {
        $response = Http::withBasicAuth($method->config['client_id'] ?? '', $method->config['client_secret'] ?? '')
            ->asForm()->post($this->base($method).'/v1/oauth2/token', ['grant_type' => 'client_credentials']);

        if ($response->failed()) {
            throw new RuntimeException('PayPal authentication failed.');
        }

        return $response->json('access_token');
    }

    public function checkoutUrl(Invoice $invoice, PaymentMethod $method): string
    {
        $decimals = Money::currencyConfig($invoice->currency)['decimals'];
        $value = number_format((float) $invoice->balance_due, $decimals, '.', '');

        $response = Http::withToken($this->token($method))->post($this->base($method).'/v2/checkout/orders', [
            'intent' => 'CAPTURE',
            'purchase_units' => [[
                'custom_id' => $invoice->public_token,
                'description' => __('Invoice :number', ['number' => $invoice->number]),
                'amount' => ['currency_code' => $invoice->currency, 'value' => $value],
            ]],
            'application_context' => ['return_url' => $invoice->publicUrl().'?paid=1', 'cancel_url' => $invoice->publicUrl(), 'user_action' => 'PAY_NOW'],
        ]);

        $link = collect($response->json('links', []))->firstWhere('rel', 'approve');

        if ($response->failed() || ! $link) {
            throw new RuntimeException('PayPal checkout could not be started.');
        }

        return $link['href'];
    }

    /** PayPal webhooks are verified by asking PayPal to validate the signature headers. */
    public function verifyWebhook(Request $request, PaymentMethod $method): bool
    {
        if (empty($method->config['webhook_id'])) {
            return false;
        }

        $response = Http::withToken($this->token($method))->post($this->base($method).'/v1/notifications/verify-webhook-signature', [
            'auth_algo' => $request->header('PAYPAL-AUTH-ALGO'),
            'cert_url' => $request->header('PAYPAL-CERT-URL'),
            'transmission_id' => $request->header('PAYPAL-TRANSMISSION-ID'),
            'transmission_sig' => $request->header('PAYPAL-TRANSMISSION-SIG'),
            'transmission_time' => $request->header('PAYPAL-TRANSMISSION-TIME'),
            'webhook_id' => $method->config['webhook_id'],
            'webhook_event' => $request->json()->all(),
        ]);

        return $response->successful() && $response->json('verification_status') === 'SUCCESS';
    }

    public function parseWebhook(Request $request): ?array
    {
        $event = $request->json()->all();
        $resource = $event['resource'] ?? [];

        return match ($event['event_type'] ?? '') {
            'PAYMENT.CAPTURE.COMPLETED' => [
                'event_id' => $event['id'], 'type' => 'payment_succeeded',
                'invoice_token' => $resource['custom_id'] ?? null,
                'transaction_id' => $resource['id'] ?? null,
                'amount' => (string) ($resource['amount']['value'] ?? '0'),
            ],
            'PAYMENT.CAPTURE.DENIED' => [
                'event_id' => $event['id'], 'type' => 'payment_failed',
                'invoice_token' => $resource['custom_id'] ?? null,
                'transaction_id' => $resource['id'] ?? null, 'amount' => '0',
            ],
            default => null,
        };
    }

    public function refund(Payment $payment, string $amount, PaymentMethod $method): bool
    {
        if (! $payment->transaction_id) {
            return true;
        }

        $decimals = Money::currencyConfig($payment->currency)['decimals'];

        return Http::withToken($this->token($method))->post($this->base($method)."/v2/payments/captures/{$payment->transaction_id}/refund", [
            'amount' => ['currency_code' => $payment->currency, 'value' => number_format((float) $amount, $decimals, '.', '')],
        ])->successful();
    }
}
