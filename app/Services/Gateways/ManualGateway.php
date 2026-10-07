<?php

namespace App\Services\Gateways;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentMethod;
use Illuminate\Http\Request;
use LogicException;

/** Cash, bank transfer and other payments that staff record by hand. */
class ManualGateway implements PaymentGatewayInterface
{
    public function key(): string
    {
        return 'manual';
    }

    public function supportsCheckout(): bool
    {
        return false;
    }

    public function checkoutUrl(Invoice $invoice, PaymentMethod $method): string
    {
        throw new LogicException('Manual payment methods have no online checkout.');
    }

    public function verifyWebhook(Request $request, PaymentMethod $method): bool
    {
        return false;
    }

    public function parseWebhook(Request $request): ?array
    {
        return null;
    }

    public function refund(Payment $payment, string $amount, PaymentMethod $method): bool
    {
        return true; // the money is returned outside the system; we only record it
    }
}
