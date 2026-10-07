<?php

namespace App\Services;

use App\Models\RecurringInvoice;
use Illuminate\Support\Facades\Cache;
use Throwable;

class RecurringInvoiceService
{
    public function __construct(private InvoiceService $invoices, private InvoiceCalculator $calculator, private TaxService $tax) {}

    /** Generate every invoice that is due today for the current workspace. Returns the number generated. */
    public function processDue(): int
    {
        $generated = 0;
        $today = today_local();

        $rules = RecurringInvoice::where('status', 'active')->whereDate('next_run_date', '<=', $today->toDateString())->get();

        foreach ($rules as $rule) {
            // A lock per rule and date stops two servers generating the same invoice
            $lock = Cache::lock("recurring:{$rule->id}:{$rule->next_run_date->toDateString()}", 600);

            if (! $lock->get()) {
                continue;
            }

            try {
                $this->generate($rule);
                $generated++;
            } catch (Throwable $exception) {
                report($exception);
            } finally {
                $lock->release();
            }
        }

        return $generated;
    }

    public function generate(RecurringInvoice $rule): void
    {
        $issueDate = today_local();

        $invoice = $this->invoices->create([
            'customer_id' => $rule->customer_id,
            'recurring_invoice_id' => $rule->id,
            'issue_date' => $issueDate->toDateString(),
            'due_date' => $issueDate->copy()->addDays($rule->due_days)->toDateString(),
            'currency' => $rule->currency,
            'notes' => $rule->notes,
            'terms' => $rule->terms,
            'items' => $rule->items,
        ]);

        if ($rule->auto_send) {
            $this->invoices->send($invoice, (bool) $invoice->customer->email);
        }

        $next = $rule->nextDateAfter($rule->next_run_date);

        // Skip periods that were missed while the rule could not run
        while ($next->lte($issueDate)) {
            $next = $rule->nextDateAfter($next);
        }

        $rule->update([
            'last_run_date' => $issueDate,
            'next_run_date' => $next,
            'invoices_generated' => $rule->invoices_generated + 1,
            'status' => $rule->end_date && $next->gt($rule->end_date) ? 'ended' : 'active',
        ]);
    }

    /** Total of one generated invoice, used by the cash-flow forecast. */
    public function amount(RecurringInvoice $rule): float
    {
        $totals = $this->calculator->calculate($this->tax->resolveLines($rule->items ?? []), [
            'currency' => $rule->currency, 'tax_inclusive' => $this->tax->pricesIncludeTax(), 'tax_exempt' => $this->tax->isExempt(),
        ]);

        return (float) $totals['total'];
    }
}
