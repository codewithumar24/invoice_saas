<?php

namespace App\Jobs;

use App\Services\BusinessHealthService;
use App\Services\InvoiceService;
use App\Services\QuoteService;
use App\Services\RecurringInvoiceService;
use App\Services\ReminderService;
use Illuminate\Support\Facades\Cache;

/**
 * Scheduled work for one workspace.
 * hourly: recurring invoices + reminders. daily: overdue detection, quote expiry, business signals.
 */
class TenantRoutineJob extends TenantJob
{
    public function __construct(int $tenantId, public string $routine)
    {
        parent::__construct($tenantId);
        $this->onQueue($routine === 'daily' ? 'analytics' : 'invoices');
    }

    public function process(RecurringInvoiceService $recurring, ReminderService $reminders, InvoiceService $invoices, QuoteService $quotes, BusinessHealthService $health): void
    {
        // One run at a time per workspace and routine, also across several servers
        $lock = Cache::lock("routine:{$this->routine}:{$this->tenantId}", 900);

        if (! $lock->get()) {
            return;
        }

        try {
            if ($this->routine === 'hourly') {
                if (feature('recurring_invoices')) {
                    $recurring->processDue();
                }

                // Reminders go out once a day, after 8:00 in the workspace timezone
                if (now(tenant()->timezone)->hour >= 8) {
                    $reminders->run();
                }
            }

            if ($this->routine === 'daily') {
                $invoices->markOverdue();
                $quotes->markExpired();

                if (feature('business_health')) {
                    $health->generate();
                }
            }
        } finally {
            $lock->release();
        }
    }
}
