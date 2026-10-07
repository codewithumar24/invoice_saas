<?php

namespace App\Services;

use App\Events\PaymentReceived;
use App\Events\PaymentRefunded;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentTransaction;
use App\Services\Gateways\GatewayManager;
use App\Support\Money;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PaymentService
{
    public function __construct(
        private InvoiceService $invoices,
        private NumberingService $numbering,
        private ActivityService $activity,
        private GatewayManager $gateways,
    ) {}

    /**
     * Record a payment against an invoice.
     * Payment + allocation + ledger transaction + invoice + customer balance succeed together or roll back together.
     */
    public function record(Invoice $invoice, array $data): Payment
    {
        $payment = DB::transaction(function () use ($invoice, $data) {
            // Lock the invoice so two payments cannot both pass the balance check
            $invoice = Invoice::whereKey($invoice->id)->lockForUpdate()->firstOrFail();
            $amount = Money::units($data['amount']);

            if ($invoice->status === 'cancelled') {
                throw ValidationException::withMessages(['amount' => __('A cancelled invoice cannot receive payments.')]);
            }

            if ($amount <= 0) {
                throw ValidationException::withMessages(['amount' => __('The amount must be greater than zero.')]);
            }

            if ($amount > Money::units($invoice->balance_due)) {
                throw ValidationException::withMessages(['amount' => __('The amount is higher than the balance due (:balance).', ['balance' => money($invoice->balance_due, $invoice->currency)])]);
            }

            if ($invoice->status === 'draft') {
                $invoice->forceFill(['status' => 'sent', 'sent_at' => now()])->save();
                app(InventoryService::class)->applyInvoice($invoice);
            }

            $payment = Payment::create([
                'customer_id' => $invoice->customer_id,
                'number' => $this->numbering->next('payment'),
                'amount' => Money::decimal($amount),
                'currency' => $invoice->currency,
                'exchange_rate' => $invoice->exchange_rate,
                'payment_method_id' => $data['payment_method_id'] ?? null,
                'method' => $data['method'] ?? 'manual',
                'gateway' => $data['gateway'] ?? null,
                'transaction_id' => $data['transaction_id'] ?? null,
                'status' => 'paid',
                'paid_at' => $data['paid_at'] ?? now(),
                'notes' => $data['notes'] ?? null,
                'created_by' => auth()->id(),
            ]);

            $payment->allocations()->create(['invoice_id' => $invoice->id, 'amount' => $payment->amount]);

            PaymentTransaction::create([
                'payment_id' => $payment->id, 'invoice_id' => $invoice->id, 'customer_id' => $invoice->customer_id,
                'type' => 'payment', 'amount' => $payment->amount, 'currency' => $payment->currency,
                'gateway' => $payment->gateway, 'gateway_reference' => $payment->transaction_id, 'status' => 'completed',
            ]);

            $this->invoices->refreshStatus($invoice);
            $this->activity->log('payment.received', __('Payment of :amount received for invoice :number', [
                'amount' => money($payment->amount, $payment->currency), 'number' => $invoice->number,
            ]), $invoice, $invoice->customer_id);

            return $payment;
        });

        PaymentReceived::dispatch($payment);

        return $payment;
    }

    /**
     * Record a payment confirmed by a gateway webhook. Idempotent: the unique index on
     * (tenant, gateway, transaction_id) means the same gateway payment is stored once, even if the webhook repeats.
     */
    public function recordFromGateway(Invoice $invoice, string $gateway, string $transactionId, string $amount, ?int $methodId = null): ?Payment
    {
        if (Payment::where('gateway', $gateway)->where('transaction_id', $transactionId)->exists()) {
            return null;
        }

        // Never record more than the open balance
        $amount = Money::decimal(min(Money::units($amount), Money::units($invoice->refresh()->balance_due)));

        if (Money::units($amount) <= 0) {
            return null;
        }

        try {
            return $this->record($invoice, [
                'amount' => $amount, 'method' => $gateway, 'gateway' => $gateway, 'transaction_id' => $transactionId,
                'payment_method_id' => $methodId, 'allow_draft' => true,
            ]);
        } catch (UniqueConstraintViolationException) {
            return null; // a parallel webhook delivery already stored it
        }
    }

    /** Refund all or part of a payment. The original payment stays in the books; a refund transaction is added. */
    public function refund(Payment $payment, string $amount, ?string $reason = null): Payment
    {
        $payment = DB::transaction(function () use ($payment, $amount, $reason) {
            $payment = Payment::whereKey($payment->id)->lockForUpdate()->firstOrFail();
            $units = Money::units($amount);
            $refundable = Money::units($payment->amount) - Money::units($payment->refunded_amount);

            if (! in_array($payment->status, ['paid', 'partially_refunded'], true)) {
                throw ValidationException::withMessages(['amount' => __('This payment cannot be refunded.')]);
            }

            if ($units <= 0 || $units > $refundable) {
                throw ValidationException::withMessages(['amount' => __('The refund must be between 0 and :max.', ['max' => money(Money::decimal($refundable), $payment->currency)])]);
            }

            // Ask the gateway first (manual methods simply return true)
            if ($payment->paymentMethod && ! $this->gateways->driver($payment->paymentMethod->type)->refund($payment, Money::decimal($units), $payment->paymentMethod)) {
                throw ValidationException::withMessages(['amount' => __('The payment gateway rejected the refund.')]);
            }

            $refunded = Money::units($payment->refunded_amount) + $units;
            $payment->forceFill([
                'refunded_amount' => Money::decimal($refunded),
                'status' => $refunded >= Money::units($payment->amount) ? 'refunded' : 'partially_refunded',
            ])->save();

            // Take the refunded amount off the invoice allocations, newest first
            $remaining = $units;
            $touched = [];

            foreach ($payment->allocations()->orderByDesc('id')->get() as $allocation) {
                if ($remaining <= 0) {
                    break;
                }

                $take = min($remaining, Money::units($allocation->amount));
                $allocation->update(['amount' => Money::decimal(Money::units($allocation->amount) - $take)]);
                $remaining -= $take;
                $touched[$allocation->invoice_id] = true;

                PaymentTransaction::create([
                    'payment_id' => $payment->id, 'invoice_id' => $allocation->invoice_id, 'customer_id' => $payment->customer_id,
                    'type' => 'refund', 'amount' => Money::decimal($take), 'currency' => $payment->currency,
                    'gateway' => $payment->gateway, 'gateway_reference' => $payment->transaction_id,
                    'status' => 'completed', 'notes' => $reason,
                ]);
            }

            foreach (Invoice::whereIn('id', array_keys($touched))->get() as $invoice) {
                $this->invoices->refreshStatus($invoice);
                $this->activity->log('payment.refunded', __('Refund of :amount on invoice :number', [
                    'amount' => money(Money::decimal($units), $payment->currency), 'number' => $invoice->number,
                ]), $invoice, $invoice->customer_id);
            }

            return $payment;
        });

        PaymentRefunded::dispatch($payment);

        return $payment;
    }
}
