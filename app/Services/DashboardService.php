<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentTransaction;
use App\Support\TenantContext;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/** Dashboard numbers. All money is converted to the workspace base currency with each document's stored rate. */
class DashboardService
{
    public function __construct(private TenantContext $context) {}

    /** [from, to] for a named period in the workspace timezone. */
    public static function range(?string $period, ?string $from = null, ?string $to = null): array
    {
        $today = today_local();

        return match ($period) {
            'today' => [$today->copy(), $today->copy()],
            '7d' => [$today->copy()->subDays(6), $today->copy()],
            '3m' => [$today->copy()->subMonthsNoOverflow(3)->addDay(), $today->copy()],
            '6m' => [$today->copy()->subMonthsNoOverflow(6)->addDay(), $today->copy()],
            '12m' => [$today->copy()->subMonthsNoOverflow(12)->addDay(), $today->copy()],
            'custom' => [Carbon::parse($from ?: $today)->startOfDay(), Carbon::parse($to ?: $today)->startOfDay()],
            default => [$today->copy()->subDays(29), $today->copy()],
        };
    }

    /** Forget cached dashboard/report numbers of a workspace (called whenever financial data changes). */
    public static function flush(?int $tenantId): void
    {
        if ($tenantId) {
            Cache::put("dashv:{$tenantId}", (string) microtime(true), 86400);
        }
    }

    private function remember(string $key, \Closure $callback): mixed
    {
        $tenantId = $this->context->id();
        $version = Cache::get("dashv:{$tenantId}", '0');

        return Cache::remember("dash:{$tenantId}:{$version}:{$key}", 600, $callback);
    }

    public function revenue(Carbon $from, Carbon $to): float
    {
        return (float) Invoice::whereNotIn('status', ['draft', 'cancelled'])
            ->whereBetween('issue_date', [$from->toDateString(), $to->toDateString()])
            ->selectRaw('COALESCE(SUM(total * exchange_rate), 0) as value')->value('value');
    }

    public function expenses(Carbon $from, Carbon $to): float
    {
        return (float) Expense::whereBetween('expense_date', [$from->toDateString(), $to->toDateString()])
            ->selectRaw('COALESCE(SUM(amount * exchange_rate), 0) as value')->value('value');
    }

    /** Money actually received in the period, net of refunds. */
    public function cashIn(Carbon $from, Carbon $to): float
    {
        return (float) Payment::whereIn('status', ['paid', 'partially_refunded', 'refunded'])
            ->whereBetween('paid_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->selectRaw('COALESCE(SUM((amount - refunded_amount) * exchange_rate), 0) as value')->value('value');
    }

    public function outstanding(): float
    {
        return (float) Invoice::open()->selectRaw('COALESCE(SUM(balance_due * exchange_rate), 0) as value')->value('value');
    }

    public function overdue(): float
    {
        return (float) Invoice::overdue()->selectRaw('COALESCE(SUM(balance_due * exchange_rate), 0) as value')->value('value');
    }

    /** Top cards, with the change against the previous period of the same length. */
    public function summary(Carbon $from, Carbon $to): array
    {
        return $this->remember("summary:{$from->toDateString()}:{$to->toDateString()}", function () use ($from, $to) {
            $days = (int) $from->diffInDays($to) + 1;
            $previousFrom = $from->copy()->subDays($days);
            $previousTo = $from->copy()->subDay();

            $revenue = $this->revenue($from, $to);
            $expenses = $this->expenses($from, $to);
            $previousRevenue = $this->revenue($previousFrom, $previousTo);
            $previousExpenses = $this->expenses($previousFrom, $previousTo);
            $cashIn = $this->cashIn($from, $to);

            return [
                'revenue' => $revenue,
                'revenue_change' => self::change($revenue, $previousRevenue),
                'expenses' => $expenses,
                'expenses_change' => self::change($expenses, $previousExpenses),
                'profit' => $revenue - $expenses,
                'profit_change' => self::change($revenue - $expenses, $previousRevenue - $previousExpenses),
                'outstanding' => $this->outstanding(),
                'overdue' => $this->overdue(),
                'overdue_count' => Invoice::overdue()->count(),
                'customers' => Customer::active()->count(),
                'cash_in' => $cashIn,
                'cash_out' => $expenses,
                'cash_net' => $cashIn - $expenses,
            ];
        });
    }

    /** Percentage change, or null when there is nothing to compare with. */
    public static function change(float $current, float $previous): ?float
    {
        return abs($previous) < 0.00001 ? null : round(($current - $previous) / abs($previous) * 100, 1);
    }

    /** Revenue and expenses per day (short ranges) or per month (long ranges), for Chart.js. */
    public function series(Carbon $from, Carbon $to): array
    {
        return $this->remember("series:{$from->toDateString()}:{$to->toDateString()}", function () use ($from, $to) {
            $daily = $from->diffInDays($to) <= 31;
            $format = $daily ? 'Y-m-d' : 'Y-m';
            $buckets = [];

            for ($cursor = $from->copy(); $cursor->lte($to); $daily ? $cursor->addDay() : $cursor->addMonthNoOverflow()->startOfMonth()) {
                $buckets[$cursor->format($format)] = ['label' => $cursor->format($daily ? 'd M' : 'M Y'), 'revenue' => 0.0, 'expenses' => 0.0, 'payments' => 0.0, 'invoices' => 0];
            }

            Invoice::whereNotIn('status', ['draft', 'cancelled'])->whereBetween('issue_date', [$from->toDateString(), $to->toDateString()])
                ->get(['issue_date', 'total', 'exchange_rate'])->each(function ($invoice) use (&$buckets, $format) {
                    $key = $invoice->issue_date->format($format);
                    if (isset($buckets[$key])) {
                        $buckets[$key]['revenue'] += (float) $invoice->total * (float) $invoice->exchange_rate;
                        $buckets[$key]['invoices']++;
                    }
                });

            Expense::whereBetween('expense_date', [$from->toDateString(), $to->toDateString()])
                ->get(['expense_date', 'amount', 'exchange_rate'])->each(function ($expense) use (&$buckets, $format) {
                    $key = $expense->expense_date->format($format);
                    if (isset($buckets[$key])) {
                        $buckets[$key]['expenses'] += (float) $expense->amount * (float) $expense->exchange_rate;
                    }
                });

            Payment::whereIn('status', ['paid', 'partially_refunded', 'refunded'])->whereBetween('paid_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
                ->get(['paid_at', 'amount', 'refunded_amount', 'exchange_rate'])->each(function ($payment) use (&$buckets, $format) {
                    $key = $payment->paid_at->timezone($this->context->timezone())->format($format);
                    if (isset($buckets[$key])) {
                        $buckets[$key]['payments'] += ((float) $payment->amount - (float) $payment->refunded_amount) * (float) $payment->exchange_rate;
                    }
                });

            $round = fn (string $column) => array_map(fn ($value) => round($value, 2), array_column($buckets, $column));

            return [
                'labels' => array_column($buckets, 'label'),
                'revenue' => $round('revenue'),
                'expenses' => $round('expenses'),
                'payments' => $round('payments'),
                'invoices' => array_column($buckets, 'invoices'),
                'profit' => array_map(fn ($bucket) => round($bucket['revenue'] - $bucket['expenses'], 2), array_values($buckets)),
            ];
        });
    }

    public function expensesByCategory(Carbon $from, Carbon $to): array
    {
        return $this->remember("expcat:{$from->toDateString()}:{$to->toDateString()}", function () use ($from, $to) {
            return Expense::with('category')->whereBetween('expense_date', [$from->toDateString(), $to->toDateString()])->get()
                ->groupBy(fn ($expense) => $expense->category?->name ?? __('Uncategorized'))
                ->map(fn ($group) => round($group->sum(fn ($expense) => (float) $expense->amount * (float) $expense->exchange_rate), 2))
                ->sortDesc()->all();
        });
    }

    public function invoiceStatusCounts(): array
    {
        return $this->remember('status', function () {
            $counts = array_fill_keys(Invoice::STATUSES, 0);

            Invoice::get(['status', 'due_date'])->each(function ($invoice) use (&$counts) {
                $counts[$invoice->displayStatus()]++;
            });

            return $counts;
        });
    }

    public function recentTransactions(int $limit = 8)
    {
        return PaymentTransaction::with(['customer', 'invoice'])->latest()->limit($limit)->get();
    }

    public function recentActivity(int $limit = 8)
    {
        return ActivityLog::with('user')->latest()->limit($limit)->get();
    }
}
