<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreQuoteRequest;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Quote;
use App\Models\TaxRate;
use App\Services\CurrencyService;
use App\Services\PdfService;
use App\Services\QuoteService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class QuoteController extends Controller
{
    public function __construct(private QuoteService $quotes) {}

    public function index(Request $request)
    {
        Gate::authorize('viewAny', Quote::class);

        $query = Quote::with('customer')
            ->when($request->query('search'), fn ($q, $term) => $q->where(fn ($q) => $q->where('number', 'like', $this->like($term))
                ->orWhereHas('customer', fn ($q) => $q->where('name', 'like', $this->like($term)))))
            ->when($request->query('status'), fn ($q, $status) => $status === 'sent' ? $q->whereIn('status', ['sent', 'viewed']) : $q->where('status', $status))
            ->when($request->query('customer_id'), fn ($q, $id) => $q->where('customer_id', $id))
            ->when($request->query('from'), fn ($q, $date) => $q->whereDate('issue_date', '>=', $date))
            ->when($request->query('to'), fn ($q, $date) => $q->whereDate('issue_date', '<=', $date));

        return view('quotes.index', [
            'quotes' => $this->sorted($query, $request, ['number', 'issue_date', 'expiry_date', 'total'], 'issue_date')->paginate($this->perPage)->withQueryString(),
            'customers' => Customer::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function create(Request $request)
    {
        Gate::authorize('create', Quote::class);
        $quote = new Quote(['customer_id' => $request->query('customer_id'), 'issue_date' => today_local(), 'expiry_date' => today_local()->addDays(30),
            'currency' => tenant()->currency, 'discount_type' => 'percent', 'terms' => setting('invoice_terms')]);

        return view('quotes.form', ['quote' => $quote, 'items' => []] + $this->formData());
    }

    public function store(StoreQuoteRequest $request): RedirectResponse
    {
        Gate::authorize('create', Quote::class);
        $quote = $this->quotes->create($request->validated());

        return $this->afterSave($request, $quote, __('Quote :number created.', ['number' => $quote->number]));
    }

    public function show(Quote $quote)
    {
        Gate::authorize('view', $quote);
        $quote->load(['customer.addresses', 'items', 'invoice']);

        return view('quotes.show', ['quote' => $quote, 'timeline' => $quote->activities()->with('user')->limit(30)->get()]);
    }

    public function edit(Quote $quote)
    {
        Gate::authorize('update', $quote);
        $quote->load('items');

        return view('quotes.form', ['quote' => $quote, 'items' => $quote->items->map->only(['product_id', 'name', 'description', 'quantity', 'unit', 'unit_price', 'discount_percent', 'tax_rate_id'])->all()] + $this->formData());
    }

    public function update(StoreQuoteRequest $request, Quote $quote): RedirectResponse
    {
        Gate::authorize('update', $quote);
        $this->quotes->update($quote, $request->validated());

        return $this->afterSave($request, $quote, __('Quote saved.'));
    }

    private function afterSave(Request $request, Quote $quote, string $message): RedirectResponse
    {
        if ($request->input('action') === 'send' && $request->user()->can('send', $quote)) {
            try {
                $this->quotes->send($quote);
                $message = __('Quote :number sent.', ['number' => $quote->number]);
            } catch (ValidationException $exception) {
                return redirect()->route('quotes.show', $quote)->with('error', collect($exception->errors())->flatten()->first());
            }
        }

        return redirect()->route('quotes.show', $quote)->with('success', $message);
    }

    public function destroy(Quote $quote): RedirectResponse
    {
        Gate::authorize('delete', $quote);
        $quote->delete();

        return redirect()->route('quotes.index')->with('success', __('Quote deleted.'));
    }

    public function send(Quote $quote): RedirectResponse
    {
        Gate::authorize('send', $quote);
        $this->quotes->send($quote);

        return back()->with('success', __('Quote sent to :email.', ['email' => $quote->customer->email]));
    }

    /** Staff can record the customer's answer when it arrived by phone or email. */
    public function respond(Request $request, Quote $quote): RedirectResponse
    {
        Gate::authorize('update', $quote);
        $data = $request->validate(['response' => ['required', 'in:accept,reject']]);
        $data['response'] === 'accept' ? $this->quotes->accept($quote) : $this->quotes->reject($quote);

        return back()->with('success', $data['response'] === 'accept' ? __('Quote marked as accepted.') : __('Quote marked as rejected.'));
    }

    public function convert(Quote $quote): RedirectResponse
    {
        Gate::authorize('invoices.create');
        Gate::authorize('view', $quote);
        $invoice = $this->quotes->convert($quote);

        return redirect()->route('invoices.edit', $invoice)->with('success', __('Quote converted to draft invoice :number.', ['number' => $invoice->number]));
    }

    public function pdf(Quote $quote, PdfService $pdf)
    {
        Gate::authorize('view', $quote);

        return response($pdf->quote($quote), 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'attachment; filename="'.$quote->number.'.pdf"']);
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
