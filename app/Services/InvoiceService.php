<?php

namespace App\Services;

use App\Events\InvoiceCreated;
use App\Events\InvoiceOverdue;
use App\Events\InvoicePaid;
use App\Events\InvoiceSent;
use App\Events\InvoiceViewed;
use App\Jobs\SendInvoiceEmailJob;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceView;
use App\Models\Quote;
use App\Models\TimeEntry;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class InvoiceService
{
    public function __construct(
        private InvoiceCalculator $calculator,
        private NumberingService $numbering,
        private TaxService $tax,
        private CurrencyService $currency,
        private InventoryService $inventory,
        private CustomerService $customers,
        private ActivityService $activity,
        private FeatureService $features,
        private UsageService $usage,
        private SettingsService $settings,
    ) {}

    public function create(array $data): Invoice
    {
        $this->features->enforceLimit('max_invoices', Invoice::where('created_at', '>=', now()->startOfMonth())->count(), __('invoices per month'));

        $invoice = DB::transaction(function () use ($data) {
            $customer = Customer::findOrFail($data['customer_id']);
            $currency = strtoupper($data['currency'] ?? $customer->currency ?? tenant()->currency);

            $invoice = new Invoice($this->attributes($data));
            $invoice->fill([
                'customer_id' => $customer->id,
                'number' => $this->numbering->next('invoice', $data['branch_id'] ?? null),
                'currency' => $currency,
                'exchange_rate' => $data['exchange_rate'] ?? $this->currency->rate($currency, $data['issue_date'] ?? null),
                'tax_inclusive' => $this->tax->pricesIncludeTax(),
                'template' => $data['template'] ?? $this->settings->get('invoice_template'),
                'status' => 'draft',
                'public_token' => Str::random(48),
                'created_by' => auth()->id(),
                'quote_id' => $data['quote_id'] ?? null,
                'recurring_invoice_id' => $data['recurring_invoice_id'] ?? null,
            ]);

            $this->applyItems($invoice, $data['items'] ?? []);
            $this->syncExtras($invoice, $data);
            $this->activity->log('invoice.created', __('Invoice :number created', ['number' => $invoice->number]), $invoice, $invoice->customer_id);

            return $invoice;
        });

        $this->usage->increment('invoices_created');
        InvoiceCreated::dispatch($invoice);

        return $invoice;
    }

    public function update(Invoice $invoice, array $data): Invoice
    {
        if (! $invoice->isEditable()) {
            throw ValidationException::withMessages(['invoice' => __('A paid or cancelled invoice cannot be edited.')]);
        }

        return DB::transaction(function () use ($invoice, $data) {
            $invoice->fill($this->attributes($data));

            if (isset($data['customer_id'])) {
                $invoice->customer_id = Customer::findOrFail($data['customer_id'])->id;
            }

            if (isset($data['currency']) && strtoupper($data['currency']) !== $invoice->currency) {
                $invoice->currency = strtoupper($data['currency']);
                $invoice->exchange_rate = $data['exchange_rate'] ?? $this->currency->rate($invoice->currency, $invoice->issue_date?->toDateString());
            }

            $this->applyItems($invoice, $data['items'] ?? []);

            if (Money::units($invoice->total) < Money::units($invoice->amount_paid)) {
                throw ValidationException::withMessages(['items' => __('The total cannot be lower than the amount already paid.')]);
            }

            $this->syncExtras($invoice, $data);
            $this->refreshStatus($invoice);

            if ($invoice->status !== 'draft') {
                $this->inventory->applyInvoice($invoice);
            }

            $this->activity->log('invoice.updated', __('Invoice :number updated', ['number' => $invoice->number]), $invoice, $invoice->customer_id);

            return $invoice;
        });
    }

    /** Recalculate every amount on the server and replace the line items. */
    private function applyItems(Invoice $invoice, array $items): void
    {
        $items = $this->tax->resolveLines(array_values($items));

        $totals = $this->calculator->calculate($items, [
            'discount_type' => $invoice->discount_type,
            'discount_value' => $invoice->discount_value,
            'shipping' => $invoice->shipping,
            'adjustment' => $invoice->adjustment,
            'tax_inclusive' => $invoice->tax_inclusive,
            'tax_exempt' => $this->tax->isExempt(),
            'currency' => $invoice->currency,
        ]);

        $invoice->fill([
            'subtotal' => $totals['subtotal'], 'discount_total' => $totals['discount_total'], 'tax_total' => $totals['tax_total'],
            'shipping' => $totals['shipping'], 'adjustment' => $totals['adjustment'], 'total' => $totals['total'],
            'balance_due' => Money::decimal(Money::units($totals['total']) - Money::units($invoice->amount_paid)),
        ]);
        $invoice->save();

        $invoice->items()->delete();
        $invoice->taxes()->delete();

        foreach ($items as $index => $item) {
            $invoice->items()->create([
                'product_id' => $item['product_id'] ?? null,
                'name' => $item['name'],
                'description' => $item['description'] ?? null,
                'quantity' => $item['quantity'],
                'unit' => $item['unit'] ?? null,
                'unit_price' => $item['unit_price'],
                'discount_percent' => $item['discount_percent'] ?? 0,
                'tax_rate_id' => $item['tax_rate_id'],
                'tax_rate' => $item['tax_rate'],
                'tax_amount' => $totals['lines'][$index]['tax_amount'],
                'line_total' => $totals['lines'][$index]['line_total'],
                'sort_order' => $index,
            ]);
        }

        foreach ($totals['taxes'] as $tax) {
            $invoice->taxes()->create(['tax_rate_id' => $tax['tax_rate_id'], 'name' => $tax['name'], 'rate' => $tax['rate'], 'amount' => $tax['amount']]);
        }
    }

    private function attributes(array $data): array
    {
        return collect($data)->only(['issue_date', 'due_date', 'reference', 'payment_terms', 'discount_type', 'discount_value',
            'shipping', 'adjustment', 'deposit_amount', 'notes', 'terms', 'footer', 'template', 'project_id', 'branch_id',
            'payment_method_id'])->map(fn ($value, $key) => in_array($key, ['discount_value', 'shipping', 'adjustment', 'deposit_amount'], true) ? ($value ?? 0) : $value)->all();
    }

    private function syncExtras(Invoice $invoice, array $data): void
    {
        if (array_key_exists('tags', $data)) {
            $invoice->syncTagNames(is_array($data['tags']) ? $data['tags'] : explode(',', (string) $data['tags']));
        }

        $invoice->saveCustomFields($data['custom_fields'] ?? null);
    }

    /** Bring amount_paid, balance_due and status in line with the recorded payments. */
    public function refreshStatus(Invoice $invoice): Invoice
    {
        $wasPaid = $invoice->status === 'paid';
        $paid = $invoice->allocations()->get()->sum(fn ($row) => Money::units($row->amount));
        $total = Money::units($invoice->total);
        $balance = $total - $paid;

        $status = $invoice->status;

        if (! in_array($status, ['cancelled'], true)) {
            if ($paid > 0 && $balance <= 0) {
                $status = 'paid';
            } elseif ($paid > 0) {
                $status = 'partially_paid';
            } elseif (in_array($status, ['paid', 'partially_paid'], true)) {
                $status = $invoice->viewed_at ? 'viewed' : 'sent';
            }

            if (in_array($status, Invoice::OPEN, true) && $invoice->due_date->lt(today_local())) {
                $status = 'overdue';
            } elseif ($status === 'overdue' && ! $invoice->due_date->lt(today_local())) {
                $status = $paid > 0 ? 'partially_paid' : 'sent';
            }
        }

        $invoice->forceFill([
            'amount_paid' => Money::decimal($paid),
            'balance_due' => Money::decimal(max($balance, 0)),
            'status' => $status,
            'paid_at' => $status === 'paid' ? ($invoice->paid_at ?? now()) : null,
        ])->save();

        $this->customers->refreshBalance($invoice->customer);

        if ($status === 'paid' && ! $wasPaid) {
            InvoicePaid::dispatch($invoice);
        }

        return $invoice;
    }

    /** Mark as sent, move stock and (optionally) queue the invoice email. */
    public function send(Invoice $invoice, bool $email = true, ?array $message = null): Invoice
    {
        if ($invoice->status === 'cancelled') {
            throw ValidationException::withMessages(['invoice' => __('A cancelled invoice cannot be sent.')]);
        }

        if ($email && ! $invoice->customer->email) {
            throw ValidationException::withMessages(['invoice' => __('This customer has no email address. Add one or mark the invoice as sent instead.')]);
        }

        DB::transaction(function () use ($invoice) {
            if ($invoice->status === 'draft') {
                $invoice->forceFill(['status' => 'sent'])->save();
            }

            $invoice->forceFill(['sent_at' => now()])->save();
            $this->inventory->applyInvoice($invoice);
            $this->refreshStatus($invoice);
        });

        if ($email) {
            SendInvoiceEmailJob::dispatch($invoice->tenant_id, $invoice->id, $message);
        }

        $this->activity->log('invoice.sent', __('Invoice :number sent', ['number' => $invoice->number]), $invoice, $invoice->customer_id);
        InvoiceSent::dispatch($invoice);

        return $invoice;
    }

    /** Called from the public invoice page. */
    public function markViewed(Invoice $invoice, ?string $userAgent = null): void
    {
        if ($this->settings->get('track_invoice_views')) {
            InvoiceView::create(['invoice_id' => $invoice->id, 'user_agent' => $userAgent ? mb_substr($userAgent, 0, 500) : null, 'viewed_at' => now()]);
        }

        if ($invoice->viewed_at === null) {
            $invoice->forceFill(['viewed_at' => now(), 'status' => $invoice->status === 'sent' ? 'viewed' : $invoice->status])->saveQuietly();
            $this->activity->log('invoice.viewed', __('Invoice :number viewed by customer', ['number' => $invoice->number]), $invoice, $invoice->customer_id);
            InvoiceViewed::dispatch($invoice);
        }
    }

    /** Financial records are never destroyed: an issued invoice is cancelled, not deleted. */
    public function cancel(Invoice $invoice): Invoice
    {
        if (Money::units($invoice->amount_paid) > 0) {
            throw ValidationException::withMessages(['invoice' => __('Refund the recorded payments before cancelling this invoice.')]);
        }

        DB::transaction(function () use ($invoice) {
            $invoice->forceFill(['status' => 'cancelled', 'cancelled_at' => now(), 'balance_due' => 0])->save();
            $this->inventory->reverseInvoice($invoice);
            $this->customers->refreshBalance($invoice->customer);
            $this->activity->log('invoice.cancelled', __('Invoice :number cancelled', ['number' => $invoice->number]), $invoice, $invoice->customer_id);
        });

        return $invoice;
    }

    /** Only drafts can be removed (soft delete). Anything issued must be cancelled instead. */
    public function delete(Invoice $invoice): void
    {
        if ($invoice->status !== 'draft') {
            throw ValidationException::withMessages(['invoice' => __('Only draft invoices can be deleted. Cancel this invoice instead.')]);
        }

        $invoice->delete();
    }

    public function duplicate(Invoice $invoice): Invoice
    {
        $dueDays = (int) $invoice->issue_date->diffInDays($invoice->due_date);

        return $this->create([
            'customer_id' => $invoice->customer_id,
            'issue_date' => today_local()->toDateString(),
            'due_date' => today_local()->addDays($dueDays)->toDateString(),
            'currency' => $invoice->currency,
            'items' => $invoice->items->map->only(['product_id', 'name', 'description', 'quantity', 'unit', 'unit_price', 'discount_percent', 'tax_rate_id'])->all(),
        ] + $invoice->only(['reference', 'payment_terms', 'discount_type', 'discount_value', 'shipping', 'adjustment', 'notes', 'terms',
            'footer', 'template', 'project_id', 'branch_id', 'payment_method_id']));
    }

    public function createFromQuote(Quote $quote): Invoice
    {
        $dueDays = (int) $this->settings->get('invoice_due_days');

        return $this->create([
            'customer_id' => $quote->customer_id,
            'quote_id' => $quote->id,
            'issue_date' => today_local()->toDateString(),
            'due_date' => today_local()->addDays($dueDays)->toDateString(),
            'currency' => $quote->currency,
            'reference' => $quote->number,
            'discount_type' => $quote->discount_type,
            'discount_value' => $quote->discount_value,
            'notes' => $quote->notes,
            'terms' => $quote->terms,
            'items' => $quote->items->map->only(['product_id', 'name', 'description', 'quantity', 'unit', 'unit_price', 'discount_percent', 'tax_rate_id'])->all(),
        ]);
    }

    /** Turn unbilled, billable time entries of a project into one draft invoice. */
    public function createFromTimeEntries(\App\Models\Project $project): Invoice
    {
        $entries = TimeEntry::where('project_id', $project->id)->where('is_billable', true)->whereNull('invoice_id')
            ->where('status', 'stopped')->where('duration_seconds', '>', 0)->with('task')->get();

        if ($entries->isEmpty() || ! $project->customer_id) {
            throw ValidationException::withMessages(['project' => __('There is no unbilled billable time for this project, or it has no customer.')]);
        }

        $items = $entries->groupBy(fn ($entry) => ($entry->project_task_id ?? 0).'|'.$entry->hourly_rate)->map(function ($group) use ($project) {
            $hours = round($group->sum('duration_seconds') / 3600, 2);
            $first = $group->first();

            return [
                'name' => $first->task?->title ?? __('Work on :project', ['project' => $project->name]),
                'description' => $group->pluck('description')->filter()->unique()->implode('; ') ?: null,
                'quantity' => $hours, 'unit' => __('hour'),
                'unit_price' => (float) $first->hourly_rate > 0 ? $first->hourly_rate : $project->hourly_rate,
            ];
        })->values()->all();

        return DB::transaction(function () use ($project, $items, $entries) {
            $invoice = $this->create([
                'customer_id' => $project->customer_id,
                'project_id' => $project->id,
                'issue_date' => today_local()->toDateString(),
                'due_date' => today_local()->addDays((int) $this->settings->get('invoice_due_days'))->toDateString(),
                'items' => $items,
            ]);

            TimeEntry::whereIn('id', $entries->pluck('id'))->update(['invoice_id' => $invoice->id]);

            return $invoice;
        });
    }

    /** Daily job: flag open invoices that passed their due date. Returns how many were flagged. */
    public function markOverdue(): int
    {
        $invoices = Invoice::whereIn('status', ['sent', 'viewed', 'partially_paid'])
            ->whereDate('due_date', '<', today_local()->toDateString())->get();

        foreach ($invoices as $invoice) {
            $invoice->forceFill(['status' => 'overdue'])->save();
            $this->activity->log('invoice.overdue', __('Invoice :number became overdue', ['number' => $invoice->number]), $invoice, $invoice->customer_id);
            InvoiceOverdue::dispatch($invoice);
        }

        return $invoices->count();
    }
}
