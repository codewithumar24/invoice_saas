<?php

namespace App\Services;

use App\Models\BusinessMetric;
use App\Models\BusinessSignal;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Payment;
use App\Models\PaymentTransaction;
use App\Models\Product;
use App\Models\Quote;
use App\Models\RecurringInvoice;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Business Health Engine: turns recorded activity into a documented score, signals and an action list.
 * Every number comes from a plain, explainable formula; there is no black box.
 */
class BusinessHealthService
{
    public function __construct(
        private DashboardService $dashboard,
        private CashFlowService $cashFlow,
        private FeatureService $features,
    ) {}

    /* ---------------------------------------------------------------------
     | Health score
     * ------------------------------------------------------------------- */

    /**
     * Category scores (0-100) and the overall score (their average).
     * Formulas are shown on the Business Health page so users can see how each score is built.
     */
    public function score(): array
    {
        $today = today_local();
        $from30 = $today->copy()->subDays(29);
        $from90 = $today->copy()->subDays(89);

        $cashIn = $this->dashboard->cashIn($from30, $today);
        $cashOut = $this->dashboard->expenses($from30, $today);
        $outstanding = $this->dashboard->outstanding();
        $overdue = $this->dashboard->overdue();
        $revenue30 = $this->dashboard->revenue($from30, $today);
        $revenuePrevious = $this->dashboard->revenue($from30->copy()->subDays(30), $from30->copy()->subDay());
        $revenue90 = $this->dashboard->revenue($from90, $today);
        $expenses90 = $this->dashboard->expenses($from90, $today);

        $activeCustomers = Customer::active()->count();
        $recentCustomers = Invoice::whereNotIn('status', ['draft', 'cancelled'])->whereDate('issue_date', '>=', $from90->toDateString())->distinct()->count('customer_id');

        $clamp = fn (float $value) => (int) max(0, min(100, round($value)));

        $categories = [
            'cash_flow' => [
                'label' => __('Cash Flow'),
                'score' => ($cashIn + $cashOut) > 0 ? $clamp(50 + 50 * ($cashIn - $cashOut) / max($cashIn, $cashOut)) : null,
                'formula' => __('Money received vs. money spent in the last 30 days.'),
            ],
            'receivables' => [
                'label' => __('Receivables'),
                'score' => $outstanding > 0 ? $clamp(100 - $overdue / $outstanding * 100) : 100,
                'formula' => __('Share of unpaid invoice value that is not overdue.'),
            ],
            'revenue_trend' => [
                'label' => __('Revenue Trend'),
                'score' => ($revenue30 + $revenuePrevious) > 0 ? $clamp(50 + ($revenuePrevious > 0 ? ($revenue30 - $revenuePrevious) / $revenuePrevious * 100 : 50)) : null,
                'formula' => __('Revenue of the last 30 days compared with the 30 days before.'),
            ],
            'expense_control' => [
                'label' => __('Expense Control'),
                'score' => $revenue90 > 0 ? $clamp((1 - $expenses90 / $revenue90) * 200) : ($expenses90 > 0 ? 0 : null),
                'formula' => __('Expenses as a share of revenue over 90 days (50% or less scores 100).'),
            ],
            'customer_activity' => [
                'label' => __('Customer Activity'),
                'score' => $activeCustomers > 0 ? $clamp($recentCustomers / $activeCustomers * 100) : null,
                'formula' => __('Share of active customers invoiced in the last 90 days.'),
            ],
            'profitability' => [
                'label' => __('Profitability'),
                'score' => $revenue90 > 0 ? $clamp(($revenue90 - $expenses90) / $revenue90 / 0.4 * 100) : null,
                'formula' => __('Profit margin over 90 days (40% or more scores 100).'),
            ],
        ];

        if ($this->features->enabled('inventory')) {
            $tracked = Product::where('type', 'product')->where('track_inventory', true)->where('status', 'active')->count();
            $categories['inventory'] = [
                'label' => __('Inventory'),
                'score' => $tracked > 0 ? $clamp(100 - Product::lowStock()->count() / $tracked * 100) : null,
                'formula' => __('Share of tracked products that are above their minimum stock.'),
            ];
        }

        $scores = collect($categories)->pluck('score')->filter(fn ($score) => $score !== null);
        $overall = $scores->count() ? (int) round($scores->avg()) : null;

        return [
            'overall' => $overall,
            'label' => match (true) {
                $overall === null => __('Not enough data yet'),
                $overall >= 75 => __('Healthy'),
                $overall >= 50 => __('Needs attention'),
                default => __('At risk'),
            },
            'categories' => $categories,
            'collection_risk' => $outstanding <= 0 ? __('Low') : ($overdue / $outstanding > 0.5 ? __('High') : ($overdue / $outstanding > 0.2 ? __('Medium') : __('Low'))),
            'cash_flow_state' => $cashIn >= $cashOut ? __('Healthy') : __('Under pressure'),
        ];
    }

    /* ---------------------------------------------------------------------
     | Signals
     * ------------------------------------------------------------------- */

    /** Detect today's signals and store them (replacing earlier undismissed signals of today). */
    public function generate(): Collection
    {
        $today = today_local();
        $dismissedCodes = BusinessSignal::whereDate('detected_on', $today->toDateString())->whereNotNull('dismissed_at')->pluck('code')->all();
        BusinessSignal::whereDate('detected_on', $today->toDateString())->whereNull('dismissed_at')->delete();

        $signals = collect($this->detect($today))->reject(fn ($signal) => in_array($signal['code'], $dismissedCodes, true));

        foreach ($signals as $signal) {
            BusinessSignal::create($signal + ['detected_on' => $today->toDateString()]);
        }

        $this->storeMetrics($today);

        return $this->current();
    }

    /** Latest undismissed signals, most severe first. */
    public function current(): Collection
    {
        $latest = BusinessSignal::max('detected_on');

        if (! $latest) {
            return collect();
        }

        return BusinessSignal::whereDate('detected_on', Carbon::parse($latest)->toDateString())->whereNull('dismissed_at')
            ->orderByDesc('severity')->orderBy('id')->get();
    }

    /** @return array<int, array> signal rows ready to insert */
    public function detect(Carbon $today): array
    {
        $signals = [];
        $add = function (string $type, string $code, string $category, string $title, ?string $message, ?string $label, ?string $url, int $severity = 1, array $data = []) use (&$signals) {
            $signals[] = ['type' => $type, 'code' => $code, 'category' => $category, 'title' => $title, 'message' => $message,
                'action_label' => $label, 'action_url' => $url, 'severity' => $severity, 'data' => $data ?: null];
        };

        $monthStart = $today->copy()->startOfMonth();
        $lastMonthStart = $monthStart->copy()->subMonthNoOverflow();
        $lastMonthEnd = $monthStart->copy()->subDay();
        $money = fn ($value) => money($value, tenant()->currency);

        // 1. Overdue invoices
        $overdue = Invoice::overdue()->with('customer')->get();
        if ($overdue->count()) {
            $long = $overdue->filter(fn ($invoice) => $invoice->daysOverdue() > 15)->count();
            $add('attention', 'overdue_invoices', 'receivables',
                trans_choice(':count invoice is overdue.|:count invoices are overdue.', $overdue->count(), ['count' => $overdue->count()]),
                $long ? trans_choice(':count of them is overdue by more than 15 days.|:count of them are overdue by more than 15 days.', $long, ['count' => $long]) : null,
                __('View overdue invoices'), route('invoices.index', ['status' => 'overdue']), $long ? 3 : 2);
        }

        // 2. Customers who repeatedly pay late
        $sixMonthsAgo = $today->copy()->subMonthsNoOverflow(6);
        $latePayers = Invoice::where('status', 'paid')->whereNotNull('paid_at')->whereDate('issue_date', '>=', $sixMonthsAgo->toDateString())->with('customer')->get()
            ->filter(fn ($invoice) => $invoice->paid_at->startOfDay()->gt($invoice->due_date))->groupBy('customer_id')->filter(fn ($group) => $group->count() >= 2);
        if ($latePayers->count()) {
            $names = $latePayers->map(fn ($group) => $group->first()->customer?->name)->filter()->take(3)->implode(', ');
            $add('risk', 'late_payers', 'receivables',
                trans_choice(':count customer repeatedly pays late.|:count customers repeatedly pay late.', $latePayers->count(), ['count' => $latePayers->count()]),
                $names, __('View customers'), route('customers.index'), 2, ['customer_ids' => $latePayers->keys()->all()]);
        }

        // 3. Unusual expense increase per category (this month vs last month)
        $thisExpenses = $this->expensesByCategory($monthStart, $today);
        $lastExpenses = $this->expensesByCategory($lastMonthStart, $lastMonthEnd);
        foreach ($thisExpenses as $category => $amount) {
            $previous = $lastExpenses[$category] ?? 0;
            if ($previous > 0 && $amount > $previous * 1.25) {
                $add('risk', 'expense_increase_'.md5($category), 'expenses',
                    __('Your :category expenses increased :percent% this month.', ['category' => mb_strtolower($category), 'percent' => round(($amount - $previous) / $previous * 100)]),
                    __(':now so far this month, :before last month.', ['now' => $money($amount), 'before' => $money($previous)]),
                    __('View expense breakdown'), route('reports.show', 'expense_by_category'), 2);
            }
        }

        // 4 + 16. Product and service performance (margin and revenue growth)
        $sales = $this->salesByProduct($monthStart, $today);
        $previousSales = $this->salesByProduct($lastMonthStart, $lastMonthEnd);
        foreach ($sales as $productId => $row) {
            if ($row['cost'] > 0 && $row['revenue'] > 0 && ($row['revenue'] - $row['cost']) / $row['revenue'] < 0.10) {
                $add('risk', 'low_margin_'.$productId, 'profitability',
                    __(':name is selling at a low profit margin.', ['name' => $row['name']]),
                    __('Margin this month: :percent%.', ['percent' => round(($row['revenue'] - $row['cost']) / $row['revenue'] * 100)]),
                    __('View product'), route('products.show', $productId), 1);
            }

            $before = $previousSales[$productId]['revenue'] ?? 0;
            if ($before > 0 && $row['revenue'] >= $before * 1.3) {
                $add('opportunity', 'product_growth_'.$productId, 'revenue',
                    __(':name has generated :percent% more revenue than last month.', ['name' => $row['name'], 'percent' => round(($row['revenue'] - $before) / $before * 100)]),
                    null, __('View sales by product'), route('reports.show', 'sales_by_product'), 1);
            }
        }

        // 5. Revenue trend over the last full months
        $months = collect(range(4, 1))->map(fn ($back) => $this->dashboard->revenue($monthStart->copy()->subMonthsNoOverflow($back), $monthStart->copy()->subMonthsNoOverflow($back - 1)->subDay()));
        $rising = $months->sliding(2)->every(fn ($pair) => $pair->last() > $pair->first());
        $falling = $months->sliding(2)->every(fn ($pair) => $pair->last() < $pair->first());
        if ($months->first() > 0 && $rising) {
            $add('trend', 'revenue_rising', 'revenue', __('Revenue has increased for the last :count months.', ['count' => 4]), null, __('View revenue report'), route('reports.show', 'revenue'), 1);
        } elseif ($months->last() > 0 && $falling) {
            $add('risk', 'revenue_declining', 'revenue', __('Revenue has declined for the last :count months.', ['count' => 4]),
                __('Review which customers stopped buying.'), __('View inactive customers'), route('reports.show', 'inactive_customers'), 3);
        }

        // Revenue vs expense growth this month (the "why" behind profit changes)
        $revenueNow = $this->dashboard->revenue($monthStart, $today);
        $revenueBefore = $this->dashboard->revenue($lastMonthStart, $lastMonthEnd);
        $expensesNow = array_sum($thisExpenses);
        $expensesBefore = array_sum($lastExpenses);
        if ($revenueBefore > 0 && $expensesBefore > 0 && $today->day >= 15) {
            $revenueGrowth = ($revenueNow - $revenueBefore) / $revenueBefore;
            $expenseGrowth = ($expensesNow - $expensesBefore) / $expensesBefore;
            if ($expenseGrowth > 0.1 && $expenseGrowth > $revenueGrowth + 0.1) {
                $add('risk', 'expenses_outpacing_revenue', 'profitability', __('Expenses are growing faster than revenue this month.'),
                    __('Expenses :expense%, revenue :revenue% against last month.', ['expense' => sprintf('%+d', round($expenseGrowth * 100)), 'revenue' => sprintf('%+d', round($revenueGrowth * 100))]),
                    __('View expense breakdown'), route('reports.show', 'expense_by_category'), 2);
            }
        }

        // 6. Inactive customers (bought before, nothing in the last 60 days)
        $inactive = $this->inactiveCustomers(60);
        if ($inactive->count()) {
            $add('attention', 'inactive_customers', 'customers',
                trans_choice(":count customer hasn't purchased in 60 days.|:count customers haven't purchased in 60 days.", $inactive->count(), ['count' => $inactive->count()]),
                $inactive->take(3)->pluck('name')->implode(', '), __('View customers'), route('reports.show', 'inactive_customers'), 1);
        }

        // 7. Customers whose purchases grew three months in a row
        $growing = $this->growingCustomers($monthStart);
        foreach ($growing->take(3) as $customer) {
            $add('opportunity', 'customer_growing_'.$customer->id, 'customers',
                __(':name has increased purchases for three consecutive months.', ['name' => $customer->name]),
                null, __('View customer'), route('customers.show', $customer), 1);
        }

        // 8. Upcoming recurring invoices
        $upcoming = RecurringInvoice::where('status', 'active')->whereDate('next_run_date', '<=', $today->copy()->addDays(7)->toDateString())->count();
        if ($upcoming) {
            $add('trend', 'upcoming_recurring', 'revenue',
                trans_choice(':count recurring invoice will be generated in the next 7 days.|:count recurring invoices will be generated in the next 7 days.', $upcoming, ['count' => $upcoming]),
                null, __('View recurring invoices'), route('recurring.index'), 1);
        }

        // 9. Subscription renewal of this workspace
        $subscription = tenant()->subscription;
        if ($subscription && in_array($subscription->status, ['trial', 'active'], true) && $subscription->current_period_end && $subscription->current_period_end->between(now(), now()->addDays(7))) {
            $add('attention', 'subscription_renewal', 'billing',
                $subscription->status === 'trial' ? __('Your trial ends on :date.', ['date' => tdate($subscription->current_period_end)]) : __('Your plan renews on :date.', ['date' => tdate($subscription->current_period_end)]),
                null, __('Open billing'), route('settings.billing'), 1);
        }

        // 10. Unusual refund activity
        $refunds = (float) PaymentTransaction::where('type', 'refund')->where('created_at', '>=', $monthStart)->sum('amount');
        $received = (float) Payment::where('paid_at', '>=', $monthStart)->sum('amount');
        if ($refunds > 0 && $received > 0 && $refunds / $received > 0.10) {
            $add('risk', 'refund_activity', 'payments', __('Refunds are :percent% of payments this month.', ['percent' => round($refunds / $received * 100)]),
                null, __('View payments'), route('payments.index'), 2);
        }

        // 11. Inventory risks
        if ($this->features->enabled('inventory')) {
            $low = Product::lowStock()->count();
            if ($low) {
                $add('attention', 'low_stock', 'inventory',
                    trans_choice(':count product is at or below its minimum stock.|:count products are at or below their minimum stock.', $low, ['count' => $low]),
                    null, __('View low stock'), route('inventory.index', ['low' => 1]), 2);
            }
        }

        // 12. Cash-flow pressure
        $forecast = $this->cashFlow->forecast()[30];
        if ($forecast['net'] < 0) {
            $add('risk', 'cash_flow_pressure', 'cash_flow', __('Projected cash flow for the next 30 days is negative.'),
                __('Expected in: :in. Expected out: :out.', ['in' => $money($forecast['receivables'] + $forecast['recurring_revenue']), 'out' => $money($forecast['recurring_expenses'] + $forecast['upcoming_expenses'])]),
                __('View cash flow'), route('health.index').'#forecast', 3);
        }

        // 13. Large unpaid invoices
        $average = (float) Invoice::whereNotIn('status', ['draft', 'cancelled'])->selectRaw('AVG(total * exchange_rate) as value')->value('value');
        if ($average > 0) {
            $large = Invoice::open()->get()->filter(fn ($invoice) => (float) $invoice->balance_due * (float) $invoice->exchange_rate > $average * 2);
            if ($large->count()) {
                $add('attention', 'large_unpaid', 'receivables',
                    trans_choice(':count large invoice is unpaid.|:count large invoices are unpaid.', $large->count(), ['count' => $large->count()]),
                    __('Together worth :amount.', ['amount' => $money($large->sum(fn ($invoice) => (float) $invoice->balance_due * (float) $invoice->exchange_rate))]),
                    __('View unpaid invoices'), route('invoices.index', ['payment' => 'unpaid', 'sort' => 'balance_due', 'direction' => 'desc']), 2);
            }
        }

        // 14. Revenue concentration
        $byCustomer = Invoice::whereNotIn('status', ['draft', 'cancelled'])->whereDate('issue_date', '>=', $today->copy()->subDays(29)->toDateString())->with('customer')->get()
            ->groupBy('customer_id')->map(fn ($group) => ['name' => $group->first()->customer?->name, 'value' => $group->sum(fn ($invoice) => (float) $invoice->total * (float) $invoice->exchange_rate)]);
        $totalRevenue = $byCustomer->sum('value');
        $top = $byCustomer->sortByDesc('value')->first();
        if ($top && $totalRevenue > 0 && $byCustomer->count() > 1 && $top['value'] / $totalRevenue >= 0.25) {
            $add('risk', 'revenue_concentration', 'revenue',
                __('Customer :name represents :percent% of your revenue in the last 30 days.', ['name' => $top['name'], 'percent' => round($top['value'] / $totalRevenue * 100)]),
                __('Relying on one customer is a risk if they leave or pay late.'), __('View sales by customer'), route('reports.show', 'sales_by_customer'), 2);
        }

        // 15. Expense concentration
        if ($expensesNow > 0 && count($thisExpenses) > 1) {
            $topCategory = array_key_first($thisExpenses);
            if ($thisExpenses[$topCategory] / $expensesNow >= 0.5) {
                $add('trend', 'expense_concentration', 'expenses',
                    __(':category makes up :percent% of this month\'s expenses.', ['category' => $topCategory, 'percent' => round($thisExpenses[$topCategory] / $expensesNow * 100)]),
                    null, __('View expense breakdown'), route('reports.show', 'expense_by_category'), 1);
            }
        }

        return $signals;
    }

    /* ---------------------------------------------------------------------
     | Action Center: "What needs your attention?"
     * ------------------------------------------------------------------- */

    /** Live counts; each item links straight to where it can be fixed. */
    public function actionCenter(): array
    {
        $user = auth()->user();
        $can = fn (string $permission) => $user?->hasPermission($permission) ?? true;
        $items = [];
        $push = function (int $count, string $singular, string $plural, string $url, string $tone = 'attention') use (&$items) {
            if ($count > 0) {
                $items[] = ['count' => $count, 'text' => trans_choice("{$singular}|{$plural}", $count, ['count' => $count]), 'url' => $url, 'tone' => $tone];
            }
        };

        if ($can('invoices.view') && $this->features->enabled('invoicing')) {
            $push(Invoice::overdue()->count(), ':count overdue invoice', ':count overdue invoices', route('invoices.index', ['status' => 'overdue']), 'danger');
        }

        if ($can('inventory.view') && $this->features->enabled('inventory')) {
            $push(Product::lowStock()->count(), ':count low-stock product', ':count low-stock products', route('inventory.index', ['low' => 1]));
        }

        if ($can('quotes.view') && $this->features->enabled('quotes')) {
            $push(Quote::whereIn('status', ['sent', 'viewed'])->count(), ':count quote awaiting response', ':count quotes awaiting response', route('quotes.index', ['status' => 'sent']), 'info');
            $push(Quote::where('status', 'accepted')->count(), ':count accepted quote to invoice', ':count accepted quotes to invoice', route('quotes.index', ['status' => 'accepted']), 'info');
        }

        if ($can('payments.view')) {
            $push(Payment::where('status', 'failed')->where('created_at', '>=', now()->subDays(14))->count(), ':count payment failed', ':count payments failed', route('payments.index', ['status' => 'failed']), 'danger');
        }

        if ($can('expenses.view') && $this->features->enabled('expenses')) {
            $push(Expense::whereNull('category_id')->count(), ':count expense needs a category', ':count expenses need a category', route('expenses.index', ['category' => 'none']));
        }

        if ($can('customers.view')) {
            $inactive = $this->inactiveCustomers(60)->count();
            if ($inactive > 0) {
                $items[] = ['count' => $inactive, 'text' => trans_choice(":count customer hasn't purchased in 60 days|:count customers haven't purchased in 60 days", $inactive, ['count' => $inactive]),
                    'url' => route('reports.show', 'inactive_customers'), 'tone' => 'info'];
            }
        }

        if ($can('invoices.view')) {
            $push(Invoice::where('status', 'draft')->count(), ':count draft invoice to send', ':count draft invoices to send', route('invoices.index', ['status' => 'draft']), 'info');
        }

        return $items;
    }

    /* ---------------------------------------------------------------------
     | Business brief (dashboard + digest email)
     * ------------------------------------------------------------------- */

    public function brief(string $period = 'monthly'): array
    {
        $today = today_local();
        $from = match ($period) {
            'daily' => $today->copy(),
            'weekly' => $today->copy()->subDays(6),
            default => $today->copy()->startOfMonth(),
        };

        $revenue = $this->dashboard->revenue($from, $today);
        $expenses = $this->dashboard->expenses($from, $today);
        $signals = $this->current();

        return [
            'revenue' => $revenue,
            'outstanding' => $this->dashboard->outstanding(),
            'overdue' => $this->dashboard->overdue(),
            'expenses' => $expenses,
            'profit' => $revenue - $expenses,
            'attention' => $signals->where('type', 'attention')->first()?->title,
            'opportunity' => $signals->where('type', 'opportunity')->first()?->title,
            'risk' => $signals->where('type', 'risk')->first()?->title,
        ];
    }

    /** Plain-text version of the brief for the digest email. */
    public function briefText(string $period): string
    {
        $brief = $this->brief($period);
        $lines = [
            __('Revenue').': '.money($brief['revenue']),
            __('Outstanding').': '.money($brief['outstanding']),
            __('Overdue').': '.money($brief['overdue']),
            __('Expenses').': '.money($brief['expenses']),
            __('Profit').': '.money($brief['profit']),
        ];

        foreach (['attention' => __('Attention'), 'opportunity' => __('Opportunity'), 'risk' => __('Risk')] as $key => $label) {
            if ($brief[$key]) {
                $lines[] = $label.': '.$brief[$key];
            }
        }

        return implode("\n", $lines);
    }

    /* ---------------------------------------------------------------------
     | Helpers
     * ------------------------------------------------------------------- */

    private function expensesByCategory(Carbon $from, Carbon $to): array
    {
        return Expense::with('category')->whereBetween('expense_date', [$from->toDateString(), $to->toDateString()])->get()
            ->groupBy(fn ($expense) => $expense->category?->name ?? __('Uncategorized'))
            ->map(fn ($group) => $group->sum(fn ($expense) => (float) $expense->amount * (float) $expense->exchange_rate))
            ->sortDesc()->all();
    }

    /** [productId => name, revenue, cost] for invoiced items in a period. */
    private function salesByProduct(Carbon $from, Carbon $to): array
    {
        return InvoiceItem::whereNotNull('product_id')->with('product')
            ->whereHas('invoice', fn ($q) => $q->whereNotIn('status', ['draft', 'cancelled'])->whereBetween('issue_date', [$from->toDateString(), $to->toDateString()]))
            ->with('invoice:id,exchange_rate')->get()->groupBy('product_id')
            ->map(fn ($group) => [
                'name' => $group->first()->product?->name ?? $group->first()->name,
                'revenue' => $group->sum(fn ($item) => (float) $item->line_total * (float) $item->invoice->exchange_rate),
                'cost' => $group->sum(fn ($item) => (float) $item->quantity * (float) ($item->product?->purchase_price ?? 0)),
            ])->all();
    }

    public function inactiveCustomers(int $days): Collection
    {
        $cutoff = today_local()->subDays($days)->toDateString();

        return Customer::active()
            ->whereHas('invoices', fn ($q) => $q->whereNotIn('status', ['draft', 'cancelled']))
            ->whereDoesntHave('invoices', fn ($q) => $q->whereNotIn('status', ['draft', 'cancelled'])->whereDate('issue_date', '>=', $cutoff))
            ->orderBy('name')->get();
    }

    /** Customers whose invoiced total rose in each of the last three full months. */
    private function growingCustomers(Carbon $monthStart): Collection
    {
        $from = $monthStart->copy()->subMonthsNoOverflow(3);

        $perMonth = Invoice::whereNotIn('status', ['draft', 'cancelled'])
            ->whereBetween('issue_date', [$from->toDateString(), $monthStart->copy()->subDay()->toDateString()])->get()
            ->groupBy('customer_id')->map(fn ($group) => $group->groupBy(fn ($invoice) => $invoice->issue_date->format('Y-m'))
                ->map(fn ($month) => $month->sum(fn ($invoice) => (float) $invoice->total * (float) $invoice->exchange_rate)));

        $keys = [$from->format('Y-m'), $from->copy()->addMonthNoOverflow()->format('Y-m'), $from->copy()->addMonthsNoOverflow(2)->format('Y-m')];

        $ids = $perMonth->filter(fn ($months) => ($months[$keys[0]] ?? 0) > 0 && ($months[$keys[1]] ?? 0) > ($months[$keys[0]] ?? 0) && ($months[$keys[2]] ?? 0) > ($months[$keys[1]] ?? 0))->keys();

        return Customer::whereIn('id', $ids)->get();
    }

    /** Daily snapshot used for long-term trend charts. */
    private function storeMetrics(Carbon $today): void
    {
        $from = $today->copy()->startOfMonth();
        $revenue = $this->dashboard->revenue($from, $today);
        $expenses = $this->dashboard->expenses($from, $today);

        $metrics = [
            'revenue_mtd' => $revenue, 'expenses_mtd' => $expenses, 'profit_mtd' => $revenue - $expenses,
            'outstanding' => $this->dashboard->outstanding(), 'overdue' => $this->dashboard->overdue(),
            'health_score' => $this->score()['overall'] ?? 0,
        ];

        foreach ($metrics as $key => $value) {
            BusinessMetric::updateOrCreate(['metric_date' => $today->toDateString(), 'key' => $key], ['value' => round($value, 4)]);
        }
    }
}
