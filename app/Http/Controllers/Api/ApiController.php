<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCustomerRequest;
use App\Http\Requests\StoreExpenseRequest;
use App\Http\Requests\StoreInvoiceRequest;
use App\Http\Requests\StorePaymentRequest;
use App\Http\Requests\StoreProductRequest;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Services\CustomerService;
use App\Services\ExpenseService;
use App\Services\InvoiceService;
use App\Services\PaymentService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * REST API v1. Authentication, scopes, rate limiting and audit logging happen in the "api.token" middleware.
 * The same services as the web app do the work, so API and UI can never disagree about business rules.
 */
class ApiController extends Controller
{
    private function page(LengthAwarePaginator $paginator, callable $map): JsonResponse
    {
        return $this->ok([
            'items' => array_map($map, $paginator->items()),
            'pagination' => ['page' => $paginator->currentPage(), 'per_page' => $paginator->perPage(), 'total' => $paginator->total(), 'last_page' => $paginator->lastPage()],
        ]);
    }

    private function perPage(Request $request): int
    {
        return max(1, min(100, (int) $request->query('per_page', 25)));
    }

    /* ------------------------------ Customers ------------------------------ */

    private function customer(Customer $c): array
    {
        return $c->only(['id', 'name', 'company', 'email', 'phone', 'tax_number', 'currency', 'status']) + ['balance' => (string) $c->balance, 'created_at' => $c->created_at?->toIso8601String()];
    }

    public function customers(Request $request): JsonResponse
    {
        $query = Customer::query()->when($request->query('search'), fn ($q, $term) => $q->where(fn ($q) => $q->where('name', 'like', $this->like($term))->orWhere('email', 'like', $this->like($term))));

        return $this->page($query->orderBy('id')->paginate($this->perPage($request)), fn ($c) => $this->customer($c));
    }

    public function showCustomer(Customer $customer): JsonResponse
    {
        return $this->ok($this->customer($customer));
    }

    public function storeCustomer(StoreCustomerRequest $request, CustomerService $customers): JsonResponse
    {
        return $this->ok($this->customer($customers->create($request->validated())), 'Customer created successfully.', 201);
    }

    public function updateCustomer(StoreCustomerRequest $request, Customer $customer, CustomerService $customers): JsonResponse
    {
        return $this->ok($this->customer($customers->update($customer, $request->validated())), 'Customer updated successfully.');
    }

    /* ------------------------------ Invoices ------------------------------ */

    private function invoice(Invoice $i, bool $withItems = false): array
    {
        $data = $i->only(['id', 'number', 'customer_id', 'reference', 'currency']) + [
            'status' => $i->displayStatus(),
            'issue_date' => $i->issue_date->toDateString(), 'due_date' => $i->due_date->toDateString(),
            'subtotal' => (string) $i->subtotal, 'discount_total' => (string) $i->discount_total, 'tax_total' => (string) $i->tax_total,
            'total' => (string) $i->total, 'amount_paid' => (string) $i->amount_paid, 'balance_due' => (string) $i->balance_due,
            'public_url' => $i->status === 'draft' ? null : $i->publicUrl(),
        ];

        if ($withItems) {
            $data['items'] = $i->items->map(fn ($item) => $item->only(['product_id', 'name', 'description', 'unit']) + [
                'quantity' => (string) $item->quantity, 'unit_price' => (string) $item->unit_price, 'discount_percent' => (string) $item->discount_percent,
                'tax_rate' => (string) $item->tax_rate, 'tax_amount' => (string) $item->tax_amount, 'line_total' => (string) $item->line_total,
            ])->all();
        }

        return $data;
    }

    public function invoices(Request $request): JsonResponse
    {
        $query = Invoice::query()
            ->when($request->query('status'), fn ($q, $status) => $q->where('status', $status))
            ->when($request->query('customer_id'), fn ($q, $id) => $q->where('customer_id', $id));

        return $this->page($query->orderByDesc('id')->paginate($this->perPage($request)), fn ($i) => $this->invoice($i));
    }

    public function showInvoice(Invoice $invoice): JsonResponse
    {
        return $this->ok($this->invoice($invoice->load('items'), true));
    }

    public function storeInvoice(StoreInvoiceRequest $request, InvoiceService $invoices): JsonResponse
    {
        $invoice = $invoices->create($request->validated());

        if ($request->input('action') === 'mark_sent') {
            $invoices->send($invoice, false);
        } elseif ($request->input('action') === 'send') {
            $invoices->send($invoice);
        }

        return $this->ok($this->invoice($invoice->refresh()->load('items'), true), 'Invoice created successfully.', 201);
    }

    /* ------------------------------ Payments ------------------------------ */

    private function payment(Payment $p): array
    {
        return $p->only(['id', 'number', 'customer_id', 'currency', 'method', 'transaction_id', 'status']) + [
            'amount' => (string) $p->amount, 'refunded_amount' => (string) $p->refunded_amount, 'paid_at' => $p->paid_at?->toIso8601String(),
        ];
    }

    public function payments(Request $request): JsonResponse
    {
        return $this->page(Payment::query()->when($request->query('customer_id'), fn ($q, $id) => $q->where('customer_id', $id))->orderByDesc('id')->paginate($this->perPage($request)), fn ($p) => $this->payment($p));
    }

    public function storePayment(StorePaymentRequest $request, Invoice $invoice, PaymentService $payments): JsonResponse
    {
        $data = $request->validated();
        $method = isset($data['payment_method_id']) ? PaymentMethod::find($data['payment_method_id']) : null;

        return $this->ok($this->payment($payments->record($invoice, $data + ['method' => $method?->type ?? 'manual'])), 'Payment recorded successfully.', 201);
    }

    /* ------------------------------ Products ------------------------------ */

    private function product(Product $p): array
    {
        return $p->only(['id', 'type', 'name', 'sku', 'barcode', 'unit', 'status']) + [
            'purchase_price' => (string) $p->purchase_price, 'selling_price' => (string) $p->selling_price, 'stock_quantity' => (string) $p->stock_quantity,
        ];
    }

    public function products(Request $request): JsonResponse
    {
        return $this->page(Product::query()->when($request->query('type'), fn ($q, $type) => $q->where('type', $type))->orderBy('id')->paginate($this->perPage($request)), fn ($p) => $this->product($p));
    }

    public function showProduct(Product $product): JsonResponse
    {
        return $this->ok($this->product($product));
    }

    public function storeProduct(StoreProductRequest $request): JsonResponse
    {
        $data = collect($request->validated())->only(['type', 'name', 'sku', 'barcode', 'description', 'category_id', 'purchase_price', 'selling_price', 'tax_rate_id', 'unit', 'track_inventory', 'minimum_stock'])->all();

        return $this->ok($this->product(Product::create(['purchase_price' => $data['purchase_price'] ?? 0, 'minimum_stock' => $data['minimum_stock'] ?? 0] + $data)->refresh()), 'Product created successfully.', 201);
    }

    /* ------------------------------ Expenses ------------------------------ */

    private function expense(Expense $e): array
    {
        return $e->only(['id', 'category_id', 'vendor', 'currency', 'payment_method', 'reference', 'notes']) + [
            'amount' => (string) $e->amount, 'tax_amount' => (string) $e->tax_amount, 'expense_date' => $e->expense_date->toDateString(),
        ];
    }

    public function expenses(Request $request): JsonResponse
    {
        $query = Expense::query()
            ->when($request->query('from'), fn ($q, $date) => $q->whereDate('expense_date', '>=', $date))
            ->when($request->query('to'), fn ($q, $date) => $q->whereDate('expense_date', '<=', $date));

        return $this->page($query->orderByDesc('id')->paginate($this->perPage($request)), fn ($e) => $this->expense($e));
    }

    public function storeExpense(StoreExpenseRequest $request, ExpenseService $expenses): JsonResponse
    {
        return $this->ok($this->expense($expenses->create($request->validated())), 'Expense created successfully.', 201);
    }
}
