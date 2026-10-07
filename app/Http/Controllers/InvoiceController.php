<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreInvoiceRequest;
use App\Http\Requests\StorePaymentRequest;
use App\Http\Requests\UpdateInvoiceRequest;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\CustomField;
use App\Models\Invoice;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Project;
use App\Models\TaxRate;
use App\Services\AuditService;
use App\Services\CurrencyService;
use App\Services\EmailTemplateService;
use App\Services\InvoiceService;
use App\Services\PaymentService;
use App\Services\PdfService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class InvoiceController extends Controller
{
    public function __construct(private InvoiceService $invoices) {}

    public function index(Request $request)
    {
        Gate::authorize('viewAny', Invoice::class);
        $today = today_local()->toDateString();

        $query = Invoice::with(['customer', 'tags'])
            ->when($request->query('search'), fn ($q, $term) => $q->where(fn ($q) => $q->where('number', 'like', $this->like($term))
                ->orWhere('reference', 'like', $this->like($term))->orWhereHas('customer', fn ($q) => $q->where('name', 'like', $this->like($term)))))
            ->when($request->query('status'), function ($q, $status) use ($today) {
                // "overdue" means open and past due, whatever the stored status says
                match ($status) {
                    'overdue' => $q->whereIn('status', Invoice::OPEN)->whereDate('due_date', '<', $today),
                    'sent', 'viewed', 'partially_paid' => $q->where('status', $status)->whereDate('due_date', '>=', $today),
                    default => $q->where('status', $status),
                };
            })
            ->when($request->query('payment') === 'unpaid', fn ($q) => $q->whereIn('status', Invoice::OPEN))
            ->when($request->query('payment') === 'paid', fn ($q) => $q->where('status', 'paid'))
            ->when($request->query('customer_id'), fn ($q, $id) => $q->where('customer_id', $id))
            ->when($request->query('currency'), fn ($q, $currency) => $q->where('currency', $currency))
            ->when($request->query('from'), fn ($q, $date) => $q->whereDate('issue_date', '>=', $date))
            ->when($request->query('to'), fn ($q, $date) => $q->whereDate('issue_date', '<=', $date))
            ->when($request->query('min'), fn ($q, $min) => $q->where('total', '>=', (float) $min))
            ->when($request->query('max'), fn ($q, $max) => $q->where('total', '<=', (float) $max));

        return view('invoices.index', [
            'invoices' => $this->sorted($query, $request, ['number', 'issue_date', 'due_date', 'total', 'balance_due', 'created_at'], 'issue_date')->paginate($this->perPage)->withQueryString(),
            'customers' => Customer::orderBy('name')->get(['id', 'name']),
            'currencies' => Invoice::distinct()->pluck('currency'),
        ]);
    }

    public function create(Request $request)
    {
        Gate::authorize('create', Invoice::class);
        $dueDays = (int) setting('invoice_due_days');

        $invoice = new Invoice([
            'customer_id' => $request->query('customer_id'),
            'project_id' => $request->query('project_id'),
            'issue_date' => today_local(),
            'due_date' => today_local()->addDays($dueDays),
            'currency' => tenant()->currency,
            'discount_type' => 'percent',
            'notes' => setting('invoice_notes'),
            'terms' => setting('invoice_terms'),
            'footer' => setting('invoice_footer'),
            'template' => setting('invoice_template'),
            'payment_terms' => __('Net :days', ['days' => $dueDays]),
        ]);

        return view('invoices.form', ['invoice' => $invoice, 'items' => []] + $this->formData());
    }

    public function store(StoreInvoiceRequest $request): RedirectResponse
    {
        Gate::authorize('create', Invoice::class);
        $invoice = $this->invoices->create($request->validated());

        return $this->afterSave($request, $invoice, __('Invoice :number created.', ['number' => $invoice->number]));
    }

    public function show(Invoice $invoice)
    {
        Gate::authorize('view', $invoice);
        $invoice->load(['customer.addresses', 'items.product', 'taxes', 'allocations.payment.paymentMethod', 'tags', 'project', 'quote', 'customFieldValues', 'creator']);

        return view('invoices.show', [
            'invoice' => $invoice,
            'timeline' => $invoice->activities()->with('user')->limit(30)->get(),
            'paymentMethods' => PaymentMethod::where('is_active', true)->orderBy('name')->get(),
            'customFields' => $invoice->customFieldsForDisplay(),
        ]);
    }

    public function edit(Invoice $invoice)
    {
        Gate::authorize('update', $invoice);

        if (! $invoice->isEditable()) {
            return redirect()->route('invoices.show', $invoice)->with('error', __('A paid or cancelled invoice cannot be edited.'));
        }

        $invoice->load(['items', 'tags', 'customFieldValues']);

        return view('invoices.form', ['invoice' => $invoice, 'items' => $invoice->items->map->only(['product_id', 'name', 'description', 'quantity', 'unit', 'unit_price', 'discount_percent', 'tax_rate_id'])->all()] + $this->formData());
    }

    public function update(UpdateInvoiceRequest $request, Invoice $invoice): RedirectResponse
    {
        Gate::authorize('update', $invoice);
        $this->invoices->update($invoice, $request->validated());

        return $this->afterSave($request, $invoice, __('Invoice saved.'));
    }

    private function afterSave(Request $request, Invoice $invoice, string $message): RedirectResponse
    {
        if ($request->input('action') === 'send' && $request->user()->can('send', $invoice)) {
            try {
                $this->invoices->send($invoice);
                $message = __('Invoice :number sent to :email.', ['number' => $invoice->number, 'email' => $invoice->customer->email]);
            } catch (ValidationException $exception) {
                return redirect()->route('invoices.show', $invoice)->with('error', collect($exception->errors())->flatten()->first());
            }
        } elseif ($request->input('action') === 'mark_sent' && $request->user()->can('send', $invoice)) {
            $this->invoices->send($invoice, false);
        }

        return redirect()->route('invoices.show', $invoice)->with('success', $message);
    }

    /** Only drafts are deleted. Issued invoices are cancelled so the accounting history stays intact. */
    public function destroy(Invoice $invoice): RedirectResponse
    {
        Gate::authorize('delete', $invoice);
        $this->invoices->delete($invoice);

        return redirect()->route('invoices.index')->with('success', __('Draft invoice deleted.'));
    }

    public function cancel(Invoice $invoice): RedirectResponse
    {
        Gate::authorize('delete', $invoice);
        $this->invoices->cancel($invoice);

        return back()->with('success', __('Invoice cancelled.'));
    }

    public function duplicate(Invoice $invoice): RedirectResponse
    {
        Gate::authorize('duplicate', $invoice);
        $copy = $this->invoices->duplicate($invoice->load('items'));

        return redirect()->route('invoices.edit', $copy)->with('success', __('Invoice duplicated as draft :number.', ['number' => $copy->number]));
    }

    /** JSON for the "preview email" dialog: subject, body, attachment name and payment link. */
    public function emailPreview(Invoice $invoice, EmailTemplateService $templates)
    {
        Gate::authorize('send', $invoice);
        $template = $templates->template('invoice_sent');
        $variables = $templates->invoiceVariables($invoice) + ['business_name' => tenant()->name, 'app_name' => config('app.name')];
        $fill = fn (string $text) => preg_replace_callback('/\{\{\s*([a-z0-9_]+)\s*\}\}/i', fn ($m) => (string) ($variables[$m[1]] ?? ''), $text);

        return $this->ok([
            'to' => $invoice->customer->email,
            'subject' => $fill($template['subject']),
            'body' => $fill($template['body']),
            'attachment' => $invoice->number.'.pdf',
            'payment_link' => $invoice->publicUrl(),
        ]);
    }

    public function send(Request $request, Invoice $invoice): RedirectResponse
    {
        Gate::authorize('send', $invoice);
        $data = $request->validate(['subject' => ['nullable', 'string', 'max:255'], 'body' => ['nullable', 'string', 'max:10000']]);
        $this->invoices->send($invoice, true, filled($data['body'] ?? null) ? $data : null);

        return back()->with('success', __('Invoice sent to :email.', ['email' => $invoice->customer->email]));
    }

    public function markSent(Invoice $invoice): RedirectResponse
    {
        Gate::authorize('send', $invoice);
        $this->invoices->send($invoice, false);

        return back()->with('success', __('Invoice marked as sent.'));
    }

    public function pdf(Invoice $invoice, PdfService $pdf)
    {
        Gate::authorize('download', $invoice);
        app(AuditService::class)->log('invoice.downloaded', $invoice->number);

        return response($pdf->invoice($invoice), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$invoice->number.'.pdf"',
        ]);
    }

    public function print(Invoice $invoice, PdfService $pdf)
    {
        Gate::authorize('download', $invoice);

        return view('invoices.print', $pdf->invoiceData($invoice) + ['pdf' => false]);
    }

    public function storePayment(StorePaymentRequest $request, Invoice $invoice, PaymentService $payments): RedirectResponse
    {
        Gate::authorize('payments.create');
        Gate::authorize('view', $invoice);
        $data = $request->validated();
        $method = isset($data['payment_method_id']) ? PaymentMethod::find($data['payment_method_id']) : null;
        $payments->record($invoice, $data + ['method' => $method?->type ?? 'manual']);

        return back()->with('success', __('Payment recorded.'));
    }

    public function bulk(Request $request, PaymentService $payments): RedirectResponse
    {
        $data = $request->validate([
            'action' => ['required', 'in:send,mark_paid,export,delete'],
            'ids' => ['required', 'array', 'min:1', 'max:200'], 'ids.*' => ['integer'],
        ]);

        if ($data['action'] === 'export') {
            Gate::authorize('reports.export');

            return redirect()->route('exports.download-now', ['type' => 'invoices', 'format' => 'csv', 'ids' => $data['ids']]);
        }

        Gate::authorize(['send' => 'invoices.send', 'mark_paid' => 'payments.create', 'delete' => 'invoices.delete'][$data['action']]);

        $done = 0;
        $skipped = 0;

        foreach (Invoice::with('customer')->whereIn('id', $data['ids'])->get() as $invoice) {
            try {
                match ($data['action']) {
                    'send' => $this->invoices->send($invoice),
                    'mark_paid' => (float) $invoice->balance_due > 0 && $invoice->status !== 'cancelled'
                        ? $payments->record($invoice, ['amount' => $invoice->balance_due, 'paid_at' => now(), 'allow_draft' => true])
                        : throw ValidationException::withMessages(['invoice' => 'nothing to pay']),
                    'delete' => $invoice->status === 'draft' ? $this->invoices->delete($invoice) : $this->invoices->cancel($invoice),
                };
                $done++;
            } catch (ValidationException) {
                $skipped++;
            }
        }

        $message = trans_choice(':count invoice processed.|:count invoices processed.', $done, ['count' => $done]);

        return back()->with($skipped && ! $done ? 'error' : 'success', $skipped ? $message.' '.__(':count skipped.', ['count' => $skipped]) : $message);
    }

    /** Lookups the invoice form needs. */
    private function formData(): array
    {
        return [
            'customers' => Customer::active()->orderBy('name')->get(['id', 'name', 'company', 'currency', 'payment_terms_days']),
            'products' => Product::active()->orderBy('name')->get(['id', 'type', 'name', 'description', 'selling_price', 'unit', 'tax_rate_id']),
            'taxRates' => TaxRate::where('is_active', true)->orderBy('name')->get(['id', 'name', 'rate', 'is_default']),
            'paymentMethods' => PaymentMethod::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'projects' => feature('projects') ? Project::whereNotIn('status', ['archived', 'cancelled'])->orderBy('name')->get(['id', 'name', 'customer_id']) : collect(),
            'branches' => Branch::orderBy('name')->get(['id', 'name']),
            'customFields' => CustomField::where('entity', 'invoice')->orderBy('sort_order')->get(),
            'currencies' => app(CurrencyService::class)->options(),
            'taxInclusive' => setting('tax_system') === 'inclusive',
        ];
    }
}
