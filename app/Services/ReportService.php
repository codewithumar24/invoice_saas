<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Expense;
use App\Models\InventoryMovement;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\InvoiceTax;
use App\Models\Payment;
use App\Models\Product;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

/**
 * Every report returns the same shape so one view, one export and one PDF template can render all of them:
 * ['title', 'columns' => [key => [label, type]], 'rows' => [...], 'totals' => [...], 'chart' => [...]|null]
 * Amounts are in the workspace base currency.
 */
class ReportService
{
    /** key => [title, group, advanced?] */
    public const REPORTS = [
        'revenue' => ['Revenue', 'Financial', false],
        'expenses' => ['Expenses', 'Financial', false],
        'profit_loss' => ['Profit', 'Financial', false],
        'cash_flow' => ['Cash flow', 'Financial', true],
        'receivables' => ['Accounts receivable', 'Financial', false],
        'payments' => ['Payments', 'Financial', false],
        'tax_summary' => ['Tax summary', 'Financial', true],
        'sales_by_customer' => ['Sales by customer', 'Sales', false],
        'sales_by_product' => ['Sales by product', 'Sales', false],
        'sales_by_service' => ['Sales by service', 'Sales', false],
        'sales_by_employee' => ['Sales by employee', 'Sales', true],
        'invoices_by_status' => ['Invoices by status', 'Invoice', false],
        'top_customers' => ['Top customers', 'Customer', false],
        'inactive_customers' => ['Inactive customers', 'Customer', true],
        'customer_growth' => ['Customer growth', 'Customer', true],
        'expense_by_category' => ['Expenses by category', 'Expense', false],
        'expense_trend' => ['Expense trend', 'Expense', false],
        'vendor_expenses' => ['Vendor expenses', 'Expense', false],
        'stock_value' => ['Stock value', 'Inventory', false],
        'low_stock' => ['Low stock', 'Inventory', false],
        'product_movement' => ['Product movement', 'Inventory', true],
    ];

    public function __construct(private DashboardService $dashboard, private BusinessHealthService $health) {}

    public function run(string $key, Carbon $from, Carbon $to, array $filters = []): array
    {
        if (! isset(self::REPORTS[$key])) {
            throw new InvalidArgumentException("Unknown report [{$key}].");
        }

        $method = 'report'.str_replace('_', '', ucwords($key, '_'));
        $report = $this->{$method}($from, $to, $filters);

        return $report + ['key' => $key, 'title' => __(self::REPORTS[$key][0]), 'totals' => [], 'chart' => null, 'from' => $from, 'to' => $to];
    }

    private function invoices(Carbon $from, Carbon $to, array $filters)
    {
        return Invoice::whereNotIn('status', ['draft', 'cancelled'])
            ->whereBetween('issue_date', [$from->toDateString(), $to->toDateString()])
            ->when($filters['customer_id'] ?? null, fn ($q, $id) => $q->where('customer_id', $id))
            ->when($filters['currency'] ?? null, fn ($q, $currency) => $q->where('currency', $currency))
            ->when($filters['branch_id'] ?? null, fn ($q, $id) => $q->where('branch_id', $id));
    }

    private function base($model, string $column = 'total'): float
    {
        return (float) $model->{$column} * (float) $model->exchange_rate;
    }

    private function col(string $label, string $type = 'text'): array
    {
        return ['label' => __($label), 'type' => $type];
    }

    private function reportRevenue(Carbon $from, Carbon $to, array $filters): array
    {
        $series = $this->dashboard->series($from, $to);
        $rows = collect($series['labels'])->map(fn ($label, $i) => ['period' => $label, 'invoices' => $series['invoices'][$i], 'revenue' => $series['revenue'][$i]])->all();

        return [
            'columns' => ['period' => $this->col('Period'), 'invoices' => $this->col('Invoices', 'number'), 'revenue' => $this->col('Revenue', 'money')],
            'rows' => $rows,
            'totals' => ['invoices' => array_sum($series['invoices']), 'revenue' => array_sum($series['revenue'])],
            'chart' => ['type' => 'bar', 'labels' => $series['labels'], 'datasets' => [['label' => __('Revenue'), 'data' => $series['revenue']]]],
        ];
    }

    private function reportExpenses(Carbon $from, Carbon $to, array $filters): array
    {
        $rows = Expense::with('category')->whereBetween('expense_date', [$from->toDateString(), $to->toDateString()])
            ->when($filters['category_id'] ?? null, fn ($q, $id) => $q->where('category_id', $id))
            ->orderBy('expense_date')->get()
            ->map(fn ($expense) => ['date' => $expense->expense_date->toDateString(), 'vendor' => $expense->vendor, 'category' => $expense->category?->name ?? __('Uncategorized'), 'amount' => $this->base($expense, 'amount')])->all();

        return [
            'columns' => ['date' => $this->col('Date', 'date'), 'vendor' => $this->col('Vendor'), 'category' => $this->col('Category'), 'amount' => $this->col('Amount', 'money')],
            'rows' => $rows,
            'totals' => ['amount' => array_sum(array_column($rows, 'amount'))],
        ];
    }

    private function reportProfitLoss(Carbon $from, Carbon $to, array $filters): array
    {
        $series = $this->dashboard->series($from, $to);
        $rows = collect($series['labels'])->map(fn ($label, $i) => ['period' => $label, 'revenue' => $series['revenue'][$i], 'expenses' => $series['expenses'][$i], 'profit' => $series['profit'][$i]])->all();

        return [
            'columns' => ['period' => $this->col('Period'), 'revenue' => $this->col('Revenue', 'money'), 'expenses' => $this->col('Expenses', 'money'), 'profit' => $this->col('Profit', 'money')],
            'rows' => $rows,
            'totals' => ['revenue' => array_sum($series['revenue']), 'expenses' => array_sum($series['expenses']), 'profit' => array_sum($series['profit'])],
            'chart' => ['type' => 'line', 'labels' => $series['labels'], 'datasets' => [
                ['label' => __('Revenue'), 'data' => $series['revenue']], ['label' => __('Expenses'), 'data' => $series['expenses']], ['label' => __('Profit'), 'data' => $series['profit']],
            ]],
        ];
    }

    private function reportCashFlow(Carbon $from, Carbon $to, array $filters): array
    {
        $series = $this->dashboard->series($from, $to);
        $rows = collect($series['labels'])->map(fn ($label, $i) => ['period' => $label, 'incoming' => $series['payments'][$i], 'outgoing' => $series['expenses'][$i], 'net' => round($series['payments'][$i] - $series['expenses'][$i], 2)])->all();

        return [
            'columns' => ['period' => $this->col('Period'), 'incoming' => $this->col('Money in', 'money'), 'outgoing' => $this->col('Money out', 'money'), 'net' => $this->col('Net', 'money')],
            'rows' => $rows,
            'totals' => ['incoming' => array_sum($series['payments']), 'outgoing' => array_sum($series['expenses']), 'net' => array_sum(array_column($rows, 'net'))],
            'chart' => ['type' => 'bar', 'labels' => $series['labels'], 'datasets' => [['label' => __('Money in'), 'data' => $series['payments']], ['label' => __('Money out'), 'data' => $series['expenses']]]],
        ];
    }

    /** Receivables aging: what is owed, grouped by how late it is. Ignores the date range (it is a snapshot). */
    private function reportReceivables(Carbon $from, Carbon $to, array $filters): array
    {
        $today = today_local();
        $rows = Invoice::open()->with('customer')->orderBy('due_date')->get()->map(function ($invoice) use ($today) {
            $days = $invoice->due_date->lt($today) ? (int) $invoice->due_date->diffInDays($today) : 0;

            return [
                'customer' => $invoice->customer?->name, 'number' => $invoice->number, 'due_date' => $invoice->due_date->toDateString(),
                'days_overdue' => $days,
                'bucket' => match (true) { $days === 0 => __('Not due'), $days <= 30 => '1-30', $days <= 60 => '31-60', $days <= 90 => '61-90', default => '90+' },
                'balance' => $this->base($invoice, 'balance_due'),
            ];
        })->all();

        $buckets = collect($rows)->groupBy('bucket')->map(fn ($group) => round($group->sum('balance'), 2));

        return [
            'columns' => ['customer' => $this->col('Customer'), 'number' => $this->col('Invoice'), 'due_date' => $this->col('Due date', 'date'), 'days_overdue' => $this->col('Days overdue', 'number'), 'bucket' => $this->col('Age (days)'), 'balance' => $this->col('Balance', 'money')],
            'rows' => $rows,
            'totals' => ['balance' => array_sum(array_column($rows, 'balance'))],
            'chart' => $buckets->count() ? ['type' => 'bar', 'labels' => $buckets->keys()->all(), 'datasets' => [['label' => __('Balance'), 'data' => $buckets->values()->all()]]] : null,
        ];
    }

    private function reportPayments(Carbon $from, Carbon $to, array $filters): array
    {
        $rows = Payment::with('customer')->whereBetween('paid_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->when($filters['customer_id'] ?? null, fn ($q, $id) => $q->where('customer_id', $id))->orderBy('paid_at')->get()
            ->map(fn ($payment) => ['date' => tdate($payment->paid_at, 'Y-m-d'), 'number' => $payment->number, 'customer' => $payment->customer?->name, 'method' => $payment->method,
                'status' => $payment->status, 'amount' => $this->base($payment, 'amount'), 'refunded' => $this->base($payment, 'refunded_amount')])->all();

        return [
            'columns' => ['date' => $this->col('Date', 'date'), 'number' => $this->col('Payment'), 'customer' => $this->col('Customer'), 'method' => $this->col('Method'), 'status' => $this->col('Status'), 'amount' => $this->col('Amount', 'money'), 'refunded' => $this->col('Refunded', 'money')],
            'rows' => $rows,
            'totals' => ['amount' => array_sum(array_column($rows, 'amount')), 'refunded' => array_sum(array_column($rows, 'refunded'))],
        ];
    }

    private function reportTaxSummary(Carbon $from, Carbon $to, array $filters): array
    {
        $invoiceIds = $this->invoices($from, $to, $filters)->pluck('exchange_rate', 'id');
        $rows = InvoiceTax::whereIn('invoice_id', $invoiceIds->keys())->get()->groupBy(fn ($tax) => $tax->name.' ('.(float) $tax->rate.'%)')
            ->map(fn ($group, $name) => ['tax' => $name, 'invoices' => $group->pluck('invoice_id')->unique()->count(), 'collected' => round($group->sum(fn ($tax) => (float) $tax->amount * (float) $invoiceIds[$tax->invoice_id]), 2)])->values()->all();

        $paid = (float) Expense::whereBetween('expense_date', [$from->toDateString(), $to->toDateString()])->selectRaw('COALESCE(SUM(tax_amount * exchange_rate), 0) as value')->value('value');

        return [
            'columns' => ['tax' => $this->col('Tax'), 'invoices' => $this->col('Invoices', 'number'), 'collected' => $this->col('Tax collected', 'money')],
            'rows' => $rows,
            'totals' => ['collected' => array_sum(array_column($rows, 'collected'))],
            'note' => __('Tax paid on expenses in this period: :amount', ['amount' => money($paid)]),
        ];
    }

    private function reportSalesByCustomer(Carbon $from, Carbon $to, array $filters): array
    {
        $rows = $this->invoices($from, $to, $filters)->with('customer')->get()->groupBy('customer_id')
            ->map(fn ($group) => ['customer' => $group->first()->customer?->name, 'invoices' => $group->count(),
                'sales' => round($group->sum(fn ($invoice) => $this->base($invoice)), 2), 'paid' => round($group->sum(fn ($invoice) => $this->base($invoice, 'amount_paid')), 2),
                'outstanding' => round($group->sum(fn ($invoice) => $this->base($invoice, 'balance_due')), 2)])
            ->sortByDesc('sales')->values()->all();

        return [
            'columns' => ['customer' => $this->col('Customer'), 'invoices' => $this->col('Invoices', 'number'), 'sales' => $this->col('Sales', 'money'), 'paid' => $this->col('Paid', 'money'), 'outstanding' => $this->col('Outstanding', 'money')],
            'rows' => $rows,
            'totals' => ['invoices' => array_sum(array_column($rows, 'invoices')), 'sales' => array_sum(array_column($rows, 'sales')), 'paid' => array_sum(array_column($rows, 'paid')), 'outstanding' => array_sum(array_column($rows, 'outstanding'))],
            'chart' => $this->topChart($rows, 'customer', 'sales'),
        ];
    }

    private function salesByItemType(string $type, Carbon $from, Carbon $to, array $filters): array
    {
        $rates = $this->invoices($from, $to, $filters)->pluck('exchange_rate', 'id');
        $rows = InvoiceItem::whereIn('invoice_id', $rates->keys())->with('product')
            ->whereHas('product', fn ($q) => $q->withTrashed()->where('type', $type)->when($filters['category_id'] ?? null, fn ($q, $id) => $q->where('category_id', $id)))
            ->get()->groupBy('product_id')
            ->map(function ($group) use ($rates) {
                $revenue = $group->sum(fn ($item) => (float) $item->line_total * (float) $rates[$item->invoice_id]);
                $cost = $group->sum(fn ($item) => (float) $item->quantity * (float) ($item->product?->purchase_price ?? 0));

                return ['item' => $group->first()->product?->name ?? $group->first()->name, 'quantity' => round($group->sum(fn ($item) => (float) $item->quantity), 2),
                    'sales' => round($revenue, 2), 'cost' => round($cost, 2), 'profit' => round($revenue - $cost, 2)];
            })->sortByDesc('sales')->values()->all();

        return [
            'columns' => ['item' => $this->col($type === 'service' ? 'Service' : 'Product'), 'quantity' => $this->col('Quantity', 'number'), 'sales' => $this->col('Sales', 'money'), 'cost' => $this->col('Cost', 'money'), 'profit' => $this->col('Profit', 'money')],
            'rows' => $rows,
            'totals' => ['sales' => array_sum(array_column($rows, 'sales')), 'cost' => array_sum(array_column($rows, 'cost')), 'profit' => array_sum(array_column($rows, 'profit'))],
            'chart' => $this->topChart($rows, 'item', 'sales'),
        ];
    }

    private function reportSalesByProduct(Carbon $from, Carbon $to, array $filters): array
    {
        return $this->salesByItemType('product', $from, $to, $filters);
    }

    private function reportSalesByService(Carbon $from, Carbon $to, array $filters): array
    {
        return $this->salesByItemType('service', $from, $to, $filters);
    }

    private function reportSalesByEmployee(Carbon $from, Carbon $to, array $filters): array
    {
        $rows = $this->invoices($from, $to, $filters)->with('creator')->get()->groupBy('created_by')
            ->map(fn ($group) => ['employee' => $group->first()->creator?->name ?? __('System'), 'invoices' => $group->count(), 'sales' => round($group->sum(fn ($invoice) => $this->base($invoice)), 2)])
            ->sortByDesc('sales')->values()->all();

        return [
            'columns' => ['employee' => $this->col('Employee'), 'invoices' => $this->col('Invoices', 'number'), 'sales' => $this->col('Sales', 'money')],
            'rows' => $rows,
            'totals' => ['invoices' => array_sum(array_column($rows, 'invoices')), 'sales' => array_sum(array_column($rows, 'sales'))],
            'chart' => $this->topChart($rows, 'employee', 'sales'),
        ];
    }

    private function reportInvoicesByStatus(Carbon $from, Carbon $to, array $filters): array
    {
        $rows = Invoice::whereBetween('issue_date', [$from->toDateString(), $to->toDateString()])->get()
            ->groupBy(fn ($invoice) => $invoice->displayStatus())
            ->map(fn ($group, $status) => ['status' => __(ucfirst(str_replace('_', ' ', $status))), 'invoices' => $group->count(),
                'total' => round($group->sum(fn ($invoice) => $this->base($invoice)), 2), 'balance' => round($group->sum(fn ($invoice) => $this->base($invoice, 'balance_due')), 2)])->values()->all();

        return [
            'columns' => ['status' => $this->col('Status'), 'invoices' => $this->col('Invoices', 'number'), 'total' => $this->col('Total', 'money'), 'balance' => $this->col('Balance', 'money')],
            'rows' => $rows,
            'totals' => ['invoices' => array_sum(array_column($rows, 'invoices')), 'total' => array_sum(array_column($rows, 'total')), 'balance' => array_sum(array_column($rows, 'balance'))],
            'chart' => ['type' => 'doughnut', 'labels' => array_column($rows, 'status'), 'datasets' => [['label' => __('Invoices'), 'data' => array_column($rows, 'invoices')]]],
        ];
    }

    private function reportTopCustomers(Carbon $from, Carbon $to, array $filters): array
    {
        $report = $this->reportSalesByCustomer($from, $to, $filters);
        $report['rows'] = array_slice($report['rows'], 0, 20);

        return $report;
    }

    private function reportInactiveCustomers(Carbon $from, Carbon $to, array $filters): array
    {
        $rows = $this->health->inactiveCustomers(60)->map(function ($customer) {
            $last = Invoice::where('customer_id', $customer->id)->whereNotIn('status', ['draft', 'cancelled'])->max('issue_date');

            return ['customer' => $customer->name, 'email' => $customer->email, 'last_invoice' => $last ? Carbon::parse($last)->toDateString() : null,
                'lifetime' => (float) Invoice::where('customer_id', $customer->id)->whereNotIn('status', ['draft', 'cancelled'])->selectRaw('COALESCE(SUM(total * exchange_rate), 0) as value')->value('value')];
        })->all();

        return [
            'columns' => ['customer' => $this->col('Customer'), 'email' => $this->col('Email'), 'last_invoice' => $this->col('Last invoice', 'date'), 'lifetime' => $this->col('Lifetime sales', 'money')],
            'rows' => $rows,
            'note' => __('Customers with earlier invoices but none in the last 60 days.'),
        ];
    }

    private function reportCustomerGrowth(Carbon $from, Carbon $to, array $filters): array
    {
        $rows = Customer::withTrashed()->whereBetween('created_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])->get()
            ->groupBy(fn ($customer) => $customer->created_at->format('Y-m'))->sortKeys()
            ->map(fn ($group, $month) => ['period' => Carbon::parse($month.'-01')->format('M Y'), 'new_customers' => $group->count()])->values()->all();

        return [
            'columns' => ['period' => $this->col('Period'), 'new_customers' => $this->col('New customers', 'number')],
            'rows' => $rows,
            'totals' => ['new_customers' => array_sum(array_column($rows, 'new_customers'))],
            'chart' => ['type' => 'line', 'labels' => array_column($rows, 'period'), 'datasets' => [['label' => __('New customers'), 'data' => array_column($rows, 'new_customers')]]],
        ];
    }

    private function reportExpenseByCategory(Carbon $from, Carbon $to, array $filters): array
    {
        $data = $this->dashboard->expensesByCategory($from, $to);
        $total = array_sum($data) ?: 1;
        $rows = collect($data)->map(fn ($amount, $category) => ['category' => $category, 'amount' => $amount, 'share' => round($amount / $total * 100, 1).'%'])->values()->all();

        return [
            'columns' => ['category' => $this->col('Category'), 'amount' => $this->col('Amount', 'money'), 'share' => $this->col('Share')],
            'rows' => $rows,
            'totals' => ['amount' => array_sum($data)],
            'chart' => ['type' => 'doughnut', 'labels' => array_keys($data), 'datasets' => [['label' => __('Expenses'), 'data' => array_values($data)]]],
        ];
    }

    private function reportExpenseTrend(Carbon $from, Carbon $to, array $filters): array
    {
        $series = $this->dashboard->series($from, $to);
        $rows = collect($series['labels'])->map(fn ($label, $i) => ['period' => $label, 'expenses' => $series['expenses'][$i]])->all();

        return [
            'columns' => ['period' => $this->col('Period'), 'expenses' => $this->col('Expenses', 'money')],
            'rows' => $rows,
            'totals' => ['expenses' => array_sum($series['expenses'])],
            'chart' => ['type' => 'line', 'labels' => $series['labels'], 'datasets' => [['label' => __('Expenses'), 'data' => $series['expenses']]]],
        ];
    }

    private function reportVendorExpenses(Carbon $from, Carbon $to, array $filters): array
    {
        $rows = Expense::whereBetween('expense_date', [$from->toDateString(), $to->toDateString()])->get()
            ->groupBy(fn ($expense) => $expense->vendor ?: __('No vendor'))
            ->map(fn ($group, $vendor) => ['vendor' => $vendor, 'expenses' => $group->count(), 'amount' => round($group->sum(fn ($expense) => $this->base($expense, 'amount')), 2)])
            ->sortByDesc('amount')->values()->all();

        return [
            'columns' => ['vendor' => $this->col('Vendor'), 'expenses' => $this->col('Expenses', 'number'), 'amount' => $this->col('Amount', 'money')],
            'rows' => $rows,
            'totals' => ['amount' => array_sum(array_column($rows, 'amount'))],
            'chart' => $this->topChart($rows, 'vendor', 'amount'),
        ];
    }

    private function reportStockValue(Carbon $from, Carbon $to, array $filters): array
    {
        $rows = Product::where('type', 'product')->where('track_inventory', true)->orderBy('name')->get()
            ->map(fn ($product) => ['product' => $product->name, 'sku' => $product->sku, 'quantity' => (float) $product->stock_quantity,
                'cost' => (float) $product->purchase_price, 'value' => round((float) $product->stock_quantity * (float) $product->purchase_price, 2)])->all();

        return [
            'columns' => ['product' => $this->col('Product'), 'sku' => $this->col('SKU'), 'quantity' => $this->col('In stock', 'number'), 'cost' => $this->col('Unit cost', 'money'), 'value' => $this->col('Stock value', 'money')],
            'rows' => $rows,
            'totals' => ['value' => array_sum(array_column($rows, 'value'))],
        ];
    }

    private function reportLowStock(Carbon $from, Carbon $to, array $filters): array
    {
        $rows = Product::lowStock()->orderBy('name')->get()
            ->map(fn ($product) => ['product' => $product->name, 'sku' => $product->sku, 'quantity' => (float) $product->stock_quantity, 'minimum' => (float) $product->minimum_stock])->all();

        return [
            'columns' => ['product' => $this->col('Product'), 'sku' => $this->col('SKU'), 'quantity' => $this->col('In stock', 'number'), 'minimum' => $this->col('Minimum', 'number')],
            'rows' => $rows,
        ];
    }

    private function reportProductMovement(Carbon $from, Carbon $to, array $filters): array
    {
        $rows = InventoryMovement::with(['product', 'location'])->whereBetween('created_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->when($filters['location_id'] ?? null, fn ($q, $id) => $q->where('location_id', $id))->latest()->limit(2000)->get()
            ->map(fn ($movement) => ['date' => tdate($movement->created_at, 'Y-m-d'), 'product' => $movement->product?->name, 'location' => $movement->location?->name,
                'type' => __(ucfirst($movement->type)), 'quantity' => (float) $movement->quantity])->all();

        return [
            'columns' => ['date' => $this->col('Date', 'date'), 'product' => $this->col('Product'), 'location' => $this->col('Location'), 'type' => $this->col('Type'), 'quantity' => $this->col('Quantity', 'number')],
            'rows' => $rows,
        ];
    }

    private function topChart(array $rows, string $labelKey, string $valueKey): ?array
    {
        $top = array_slice($rows, 0, 10);

        return $top ? ['type' => 'bar', 'labels' => array_column($top, $labelKey), 'datasets' => [['label' => __('Amount'), 'data' => array_column($top, $valueKey)]]] : null;
    }
}
