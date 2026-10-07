<?php

namespace App\Services;

use App\Models\Expense;
use App\Models\Invoice;
use App\Models\RecurringInvoice;

/**
 * Cash-flow projection for the next 30, 60 and 90 days from known data only:
 * open invoices + expected recurring revenue - recurring expenses - known upcoming expenses.
 * This is a projection, not financial advice, and the UI labels it that way.
 */
class CashFlowService
{
    public function __construct(private RecurringInvoiceService $recurring) {}

    public function forecast(): array
    {
        $today = today_local();
        $openInvoices = Invoice::open()->get(['due_date', 'balance_due', 'exchange_rate']);
        $rules = RecurringInvoice::where('status', 'active')->get();
        $recurringExpenses = $this->latestRecurringExpenses();
        $futureExpenses = Expense::whereDate('expense_date', '>', $today->toDateString())->get(['expense_date', 'amount', 'exchange_rate']);
        $result = [];

        foreach ([30, 60, 90] as $days) {
            $end = $today->copy()->addDays($days);

            // Overdue invoices are expected inside the first window
            $receivables = $openInvoices->filter(fn ($invoice) => $invoice->due_date->lte($end))
                ->sum(fn ($invoice) => (float) $invoice->balance_due * (float) $invoice->exchange_rate);

            $recurringRevenue = $rules->sum(function ($rule) use ($today, $end) {
                $runs = 0;

                for ($date = $rule->next_run_date->copy(); $date->lte($end) && $runs < 200; $date = $rule->nextDateAfter($date)) {
                    if ($rule->end_date && $date->gt($rule->end_date)) {
                        break;
                    }
                    if ($date->gte($today)) {
                        $runs++;
                    }
                }

                return $runs * $this->recurring->amount($rule);
            });

            $recurringOut = $recurringExpenses->sum(function ($expense) use ($days) {
                $perDays = ['weekly' => 7, 'monthly' => 30, 'quarterly' => 90, 'yearly' => 365][$expense->recurring_frequency] ?? 30;

                return floor($days / $perDays) * (float) $expense->amount * (float) $expense->exchange_rate;
            });

            $upcoming = $futureExpenses->filter(fn ($expense) => $expense->expense_date->lte($end))
                ->sum(fn ($expense) => (float) $expense->amount * (float) $expense->exchange_rate);

            $result[$days] = [
                'receivables' => round($receivables, 2),
                'recurring_revenue' => round($recurringRevenue, 2),
                'recurring_expenses' => round($recurringOut, 2),
                'upcoming_expenses' => round($upcoming, 2),
                'net' => round($receivables + $recurringRevenue - $recurringOut - $upcoming, 2),
            ];
        }

        return $result;
    }

    /** The most recent entry of each recurring expense (same vendor + category) stands for the series. */
    private function latestRecurringExpenses()
    {
        return Expense::where('is_recurring', true)->orderByDesc('expense_date')->get()
            ->unique(fn ($expense) => mb_strtolower((string) $expense->vendor).'|'.$expense->category_id);
    }
}
