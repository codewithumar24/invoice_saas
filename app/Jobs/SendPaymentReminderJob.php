<?php

namespace App\Jobs;

use App\Models\Invoice;
use App\Services\EmailTemplateService;
use App\Services\NotificationService;

class SendPaymentReminderJob extends TenantJob
{
    public function __construct(int $tenantId, public int $invoiceId, public string $type, public int $days = 0)
    {
        parent::__construct($tenantId);
        $this->onQueue('emails');
    }

    public function process(NotificationService $notifications, EmailTemplateService $templates): void
    {
        $invoice = Invoice::with('customer')->find($this->invoiceId);

        // The invoice may have been paid between scheduling and sending
        if (! $invoice || ! in_array($invoice->status, Invoice::OPEN, true) || ! $invoice->customer?->email) {
            return;
        }

        $template = ['before_due' => 'reminder_before_due', 'due' => 'reminder_due', 'overdue' => 'invoice_overdue'][$this->type] ?? 'invoice_overdue';

        $notifications->email($invoice->customer->email, $template, $templates->invoiceVariables($invoice) + ['days' => $this->days],
            [], $invoice->publicUrl(), __('Pay invoice'));
    }
}
