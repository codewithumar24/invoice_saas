<?php

namespace App\Services\Gateways;

/** Resolves a payment method type to its gateway driver. */
class GatewayManager
{
    /** Register new gateways here. */
    private array $drivers = [
        'stripe' => StripeGateway::class,
        'paypal' => PayPalGateway::class,
        'custom' => CustomGateway::class,
    ];

    public function driver(string $type): PaymentGatewayInterface
    {
        // cash, bank_transfer, manual and anything unknown are handled by hand
        return app($this->drivers[$type] ?? ManualGateway::class);
    }

    public function types(): array
    {
        return [
            'cash' => 'Cash', 'bank_transfer' => 'Bank transfer', 'manual' => 'Other (manual)',
            'stripe' => 'Stripe', 'paypal' => 'PayPal', 'custom' => 'Custom gateway',
        ];
    }
}
