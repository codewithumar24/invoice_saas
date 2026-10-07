<?php

namespace App\Services;

use App\Jobs\SendPaymentReminderJob;
use App\Models\Invoice;
use App\Models\InvoiceReminder;
use Illuminate\Database\UniqueConstraintViolationException;

/** Automatic invoice reminders: before the due date, on the due date and after it. Configurable per workspace. */
class ReminderService
{
    public function __construct(private SettingsService $settings) {}

    public function run(): int
    {
        if (! $this->settings->get('reminders_enabled')) {
            return 0;
        }

        $today = today_local();
        $sent = 0;
        $before = (int) $this->settings->get('reminder_before_days');
        $after = array_map('intval', (array) $this->settings->get('reminder_after_days'));

        $invoices = Invoice::open()->with('customer')->get()->filter(fn ($invoice) => $invoice->customer?->email);

        foreach ($invoices as $invoice) {
            $days = (int) round($today->diffInDays($invoice->due_date, false)); // positive = days until due

            $type = match (true) {
                $before > 0 && $days === $before => 'before_due',
                $days === 0 && $this->settings->get('reminder_on_due') => 'due',
                $days < 0 && in_array(abs($days), $after, true) => 'overdue',
                default => null,
            };

            if ($type && $this->claim($invoice, $type, $today->toDateString())) {
                SendPaymentReminderJob::dispatch($invoice->tenant_id, $invoice->id, $type, abs($days));
                $sent++;
            }
        }

        return $sent;
    }

    /** The unique index guarantees one reminder per invoice, type and day. */
    private function claim(Invoice $invoice, string $type, string $date): bool
    {
        try {
            InvoiceReminder::create(['invoice_id' => $invoice->id, 'type' => $type, 'sent_on' => $date]);

            return true;
        } catch (UniqueConstraintViolationException) {
            return false;
        }
    }
}
