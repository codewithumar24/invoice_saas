<?php

namespace App\Jobs;

use App\Models\Payment;
use App\Services\EmailTemplateService;
use App\Services\NotificationService;

class SendPaymentReceiptJob extends TenantJob
{
    public function __construct(int $tenantId, public int $paymentId)
    {
        parent::__construct($tenantId);
        $this->onQueue('emails');
    }

    public function process(NotificationService $notifications, EmailTemplateService $templates): void
    {
        $payment = Payment::with(['customer', 'invoices'])->find($this->paymentId);
        $invoice = $payment?->invoices->first();

        if (! $payment || ! $invoice || ! $payment->customer?->email) {
            return;
        }

        $notifications->email($payment->customer->email, 'payment_received',
            $templates->invoiceVariables($invoice->load('customer')) + ['payment_amount' => money($payment->amount, $payment->currency)],
            [], $invoice->publicUrl(), __('View invoice'));
    }
}
