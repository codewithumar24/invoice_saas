<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessRecurringInvoiceJob;
use App\Models\Customer;
use App\Models\Product;
use App\Models\RecurringInvoice;
use App\Models\TaxRate;
use App\Services\CurrencyService;
use App\Services\RecurringInvoiceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RecurringInvoiceController extends Controller
{
    public function index(RecurringInvoiceService $recurring)
    {
        $rules = RecurringInvoice::with('customer')->orderBy('next_run_date')->paginate($this->perPage);
        $rules->getCollection()->each(fn ($rule) => $rule->amount = $recurring->amount($rule));

        return view('recurring.index', ['rules' => $rules]);
    }

    public function create()
    {
        return view('recurring.form', ['rule' => new RecurringInvoice(['frequency' => 'monthly', 'start_date' => today_local(), 'due_days' => (int) setting('invoice_due_days'), 'auto_send' => true, 'currency' => tenant()->currency]), 'items' => []] + $this->formData());
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        RecurringInvoice::create($data + ['next_run_date' => $data['start_date'], 'created_by' => $request->user()->id]);

        return redirect()->route('recurring.index')->with('success', __('Recurring invoice created. The first invoice is generated on :date.', ['date' => tdate($data['start_date'])]));
    }

    public function edit(RecurringInvoice $recurring)
    {
        return view('recurring.form', ['rule' => $recurring, 'items' => $recurring->items ?? []] + $this->formData());
    }

    public function update(Request $request, RecurringInvoice $recurring): RedirectResponse
    {
        $data = $this->validated($request);

        // A changed start date in the future moves the next run
        if ($recurring->invoices_generated === 0) {
            $data['next_run_date'] = $data['start_date'];
        }

        $recurring->update($data);

        return redirect()->route('recurring.index')->with('success', __('Recurring invoice saved.'));
    }

    public function toggle(RecurringInvoice $recurring): RedirectResponse
    {
        if ($recurring->status !== 'ended') {
            $recurring->update(['status' => $recurring->status === 'active' ? 'paused' : 'active']);
        }

        return back()->with('success', $recurring->status === 'active' ? __('Recurring invoice resumed.') : __('Recurring invoice paused.'));
    }

    public function run(RecurringInvoice $recurring): RedirectResponse
    {
        ProcessRecurringInvoiceJob::dispatch($recurring->tenant_id, $recurring->id);

        return back()->with('success', __('The next invoice is being generated.'));
    }

    public function destroy(RecurringInvoice $recurring): RedirectResponse
    {
        // Generated invoices stay; only the rule is removed
        $recurring->delete();

        return redirect()->route('recurring.index')->with('success', __('Recurring invoice deleted. Invoices already generated are kept.'));
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'customer_id' => ['required', 'integer', tenant_exists('customers')],
            'title' => ['nullable', 'string', 'max:255'],
            'frequency' => ['required', Rule::in(RecurringInvoice::FREQUENCIES)],
            'interval_days' => ['nullable', 'required_if:frequency,custom', 'integer', 'min:1', 'max:3650'],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'due_days' => ['required', 'integer', 'min:0', 'max:365'],
            'currency' => ['required', Rule::in(array_keys(config('currencies')))],
            'auto_send' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'terms' => ['nullable', 'string', 'max:5000'],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.product_id' => ['nullable', 'integer', tenant_exists('products')],
            'items.*.name' => ['required', 'string', 'max:255'],
            'items.*.description' => ['nullable', 'string', 'max:2000'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.unit' => ['nullable', 'string', 'max:50'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'items.*.discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'items.*.tax_rate_id' => ['nullable', 'integer', tenant_exists('tax_rates')],
        ]);

        $data['auto_send'] = $request->boolean('auto_send');
        $data['items'] = array_values($data['items']);

        return $data;
    }

    private function formData(): array
    {
        return [
            'customers' => Customer::active()->orderBy('name')->get(['id', 'name', 'company', 'currency', 'payment_terms_days']),
            'products' => Product::active()->orderBy('name')->get(['id', 'type', 'name', 'description', 'selling_price', 'unit', 'tax_rate_id']),
            'taxRates' => TaxRate::where('is_active', true)->orderBy('name')->get(['id', 'name', 'rate', 'is_default']),
            'currencies' => app(CurrencyService::class)->options(),
            'taxInclusive' => setting('tax_system') === 'inclusive',
        ];
    }
}
