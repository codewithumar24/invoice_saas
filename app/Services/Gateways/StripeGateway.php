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
 * Stripe Checkout through the REST API (no SDK needed).
 * Method config: secret_key, webhook_secret.
 */
class StripeGateway implements PaymentGatewayInterface
{
    private const API = 'https://api.stripe.com/v1';

    public function key(): string
    {
        return 'stripe';
    }

    public function supportsCheckout(): bool
    {
        return true;
    }

    public function checkoutUrl(Invoice $invoice, PaymentMethod $method): string
    {
        $decimals = Money::currencyConfig($invoice->currency)['decimals'];
        // Stripe expects the smallest currency unit (cents)
        $amount = (int) (Money::roundTo(Money::units($invoice->balance_due), $decimals) / (Money::SCALE / (10 ** $decimals)));

        $response = Http::withToken($method->config['secret_key'] ?? '')->asForm()->post(self::API.'/checkout/sessions', [
            'mode' => 'payment',
            'success_url' => $invoice->publicUrl().'?paid=1',
            'cancel_url' => $invoice->publicUrl(),
            'client_reference_id' => $invoice->public_token,
            'customer_email' => $invoice->customer->email,
            'line_items' => [[
                'quantity' => 1,
                'price_data' => [
                    'currency' => strtolower($invoice->currency),
                    'unit_amount' => $amount,
                    'product_data' => ['name' => __('Invoice :number', ['number' => $invoice->number])],
                ],
            ]],
            'metadata' => ['invoice_token' => $invoice->public_token],
            'payment_intent_data' => ['metadata' => ['invoice_token' => $invoice->public_token]],
        ]);

        if ($response->failed() || ! $response->json('url')) {
            throw new RuntimeException('Stripe checkout could not be started: '.$response->json('error.message', 'unknown error'));
        }

        return $response->json('url');
    }

    public function verifyWebhook(Request $request, PaymentMethod $method): bool
    {
        return self::validSignature($request->getContent(), (string) $request->header('Stripe-Signature'), (string) ($method->config['webhook_secret'] ?? ''));
    }

    /** Stripe signs "timestamp.payload" with HMAC-SHA256; reject old timestamps to stop replays. */
    public static function validSignature(string $payload, string $header, string $secret, int $tolerance = 300): bool
    {
        if ($secret === '' || $header === '') {
            return false;
        }

        $timestamp = null;
        $signatures = [];

        foreach (explode(',', $header) as $part) {
            [$name, $value] = array_pad(explode('=', trim($part), 2), 2, '');
            if ($name === 't') {
                $timestamp = $value;
            } elseif ($name === 'v1') {
                $signatures[] = $value;
            }
        }

        if (! $timestamp || abs(time() - (int) $timestamp) > $tolerance) {
            return false;
        }

        $expected = hash_hmac('sha256', $timestamp.'.'.$payload, $secret);

        foreach ($signatures as $signature) {
            if (hash_equals($expected, $signature)) {
                return true;
            }
        }

        return false;
    }

    public function parseWebhook(Request $request): ?array
    {
        $event = $request->json()->all();
        $object = $event['data']['object'] ?? [];
        $type = $event['type'] ?? '';

        if ($type === 'checkout.session.completed' && ($object['payment_status'] ?? '') === 'paid') {
            $decimals = Money::currencyConfig(strtoupper($object['currency'] ?? 'USD'))['decimals'];

            return [
                'event_id' => $event['id'],
                'type' => 'payment_succeeded',
                'invoice_token' => $object['metadata']['invoice_token'] ?? $object['client_reference_id'] ?? null,
                'transaction_id' => $object['payment_intent'] ?? $object['id'],
                'amount' => Money::decimal((int) ($object['amount_total'] ?? 0) * (int) (Money::SCALE / (10 ** $decimals))),
            ];
        }

        if ($type === 'payment_intent.payment_failed') {
            return [
                'event_id' => $event['id'], 'type' => 'payment_failed',
                'invoice_token' => $object['metadata']['invoice_token'] ?? null,
                'transaction_id' => $object['id'] ?? null, 'amount' => '0',
            ];
        }

        return null;
    }

    public function refund(Payment $payment, string $amount, PaymentMethod $method): bool
    {
        if (! $payment->transaction_id) {
            return true;
        }

        $decimals = Money::currencyConfig($payment->currency)['decimals'];

        return Http::withToken($method->config['secret_key'] ?? '')->asForm()->post(self::API.'/refunds', [
            'payment_intent' => $payment->transaction_id,
            'amount' => (int) (Money::units($amount) / (Money::SCALE / (10 ** $decimals))),
        ])->successful();
    }
}
